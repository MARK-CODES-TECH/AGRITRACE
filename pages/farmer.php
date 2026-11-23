<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Universal directory resolver: works both in root (flat) and in subfolder (/pages/)
$baseDir = file_exists(__DIR__ . '/helpers.php') ? __DIR__ : dirname(__DIR__);
chdir($baseDir);

require_once $baseDir . '/helpers.php';
require_once $baseDir . '/init.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_role'] ?? '') !== 'Farmer') {
    header('Location: login.php'); 
    exit;
}

try {
    if (isset($pdo)) {
        ensure_agritrace_extended_tables($pdo);
        if (!function_exists('ensure_assistance_tables')) {
    function ensure_assistance_tables($pdo) {
        if (!$pdo) return;
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            // 1. Create tables if they do not exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS `farmer_assistance_requests` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `reference_no` varchar(50) NOT NULL UNIQUE,
              `farmer_id` int(11) NOT NULL,
              `assistance_type` enum('Veterinary Assistance','Slaughter Assistance') NOT NULL DEFAULT 'Veterinary Assistance',
              `status` enum('Request Submitted','Pending Review','Under Review','For Inspection','Approved','Rejected','Certificate Processing','Certificate Issued','Completed') NOT NULL DEFAULT 'Request Submitted',
              `official_remarks` text DEFAULT NULL,
              `assigned_officer_id` int(11) DEFAULT NULL,
              `municipality` varchar(100) NOT NULL DEFAULT 'Ragay',
              `farmer_name` varchar(255) NOT NULL,
              `farmer_contact` varchar(50) DEFAULT NULL,
              `farmer_address` text DEFAULT NULL,
              `farmer_barangay` varchar(100) DEFAULT NULL,
              `farmer_municipality` varchar(100) DEFAULT NULL,
              `animal_species` varchar(100) NOT NULL,
              `animal_species_other` varchar(100) DEFAULT NULL,
              `head_count` int(11) NOT NULL DEFAULT 1,
              `sex` enum('Male','Female','Mixed') NOT NULL DEFAULT 'Mixed',
              `male_count` int(11) DEFAULT 0,
              `female_count` int(11) DEFAULT 0,
              `animal_age` varchar(100) DEFAULT NULL,
              `purpose` varchar(100) NOT NULL DEFAULT 'Slaughter',
              `purpose_other` varchar(100) DEFAULT NULL,
              `estimated_value` decimal(12,2) DEFAULT 0.00,
              `animal_description` text DEFAULT NULL,
              `origin_barangay` varchar(100) DEFAULT NULL,
              `origin_municipality` varchar(100) DEFAULT NULL,
              `dest_barangay` varchar(100) DEFAULT NULL,
              `dest_municipality` varchar(100) DEFAULT NULL,
              `dest_address` text DEFAULT NULL,
              `transport_means` varchar(100) DEFAULT NULL,
              `transport_means_other` varchar(100) DEFAULT NULL,
              `plate_number` varchar(50) DEFAULT NULL,
              `shipper_name` varchar(255) DEFAULT NULL,
              `shipper_address` text DEFAULT NULL,
              `shipper_contact` varchar(50) DEFAULT NULL,
              `proposed_shipment_date` date DEFAULT NULL,
              `transport_details` text DEFAULT NULL,
              `has_treatment` enum('Yes','No') NOT NULL DEFAULT 'No',
              `treatment_history` text DEFAULT NULL,
              `attachment_url` varchar(500) DEFAULT NULL,
              `request_description` text NOT NULL,
              `certified_accurate` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_far_farmer` (`farmer_id`),
              KEY `idx_far_status` (`status`),
              KEY `idx_far_muni` (`municipality`),
              KEY `idx_far_type` (`assistance_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `assistance_certificates` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `request_id` int(11) NOT NULL,
              `request_ref` varchar(50) NOT NULL,
              `farmer_id` int(11) NOT NULL,
              `cert_type` enum('VHC','COI') NOT NULL,
              `control_number` varchar(100) NOT NULL UNIQUE,
              `issue_date` date NOT NULL,
              `expiry_date` date DEFAULT NULL,
              `municipality` varchar(100) NOT NULL DEFAULT 'Ragay',
              `place_of_issue` varchar(255) NOT NULL DEFAULT 'Ragay, Camarines Sur',
              `vet_name` varchar(255) NOT NULL DEFAULT 'Dr. Maria Santos, DVM',
              `vet_position` varchar(100) DEFAULT 'Municipal Veterinarian',
              `verified_species` varchar(100) DEFAULT NULL,
              `verified_heads` int(11) NOT NULL DEFAULT 1,
              `verified_sex` varchar(50) DEFAULT NULL,
              `verified_age` varchar(50) DEFAULT NULL,
              `purpose` varchar(100) DEFAULT NULL,
              `origin` varchar(255) DEFAULT NULL,
              `destination` varchar(255) DEFAULT NULL,
              `treatment_verification` text DEFAULT NULL,
              `value` decimal(12,2) DEFAULT 0.00,
              `transport_means` varchar(100) DEFAULT NULL,
              `plate_number` varchar(50) DEFAULT NULL,
              `shipper` varchar(255) DEFAULT NULL,
              `shipper_address` text DEFAULT NULL,
              `or_number` varchar(100) DEFAULT NULL,
              `amount_paid` decimal(10,2) DEFAULT 0.00,
              `payment_date` date DEFAULT NULL,
              `status` enum('Issued','Revoked') DEFAULT 'Issued',
              `created_by` int(11) DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_ac_req` (`request_id`),
              KEY `idx_ac_farmer` (`farmer_id`),
              KEY `idx_ac_type` (`cert_type`),
              KEY `idx_ac_ctrl` (`control_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `officer_profiles` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `profile_pix` varchar(255) DEFAULT NULL,
              `gov_id` varchar(100) DEFAULT NULL,
              `department` varchar(255) DEFAULT NULL,
              `position` varchar(255) DEFAULT NULL,
              `office` varchar(255) DEFAULT NULL,
              `assigned_region` varchar(255) DEFAULT NULL,
              `municipality` varchar(255) DEFAULT NULL,
              `province` varchar(255) DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_officer_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Safe column additions for assistance_certificates
            $ensureCol = function($table, $col, $def) use ($pdo) {
                try {
                    $cols = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'")->fetchAll();
                    if (empty($cols)) {
                        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
                    }
                } catch (Throwable $e) {}
            };

            $ensureCol('assistance_certificates', 'municipality', "VARCHAR(100) NOT NULL DEFAULT 'Ragay'");
            $ensureCol('assistance_certificates', 'place_of_issue', "VARCHAR(255) NOT NULL DEFAULT 'Ragay, Camarines Sur'");
            $ensureCol('assistance_certificates', 'vet_name', "VARCHAR(255) NOT NULL DEFAULT 'Dr. Maria Santos, DVM'");
            $ensureCol('assistance_certificates', 'vet_position', "VARCHAR(100) DEFAULT 'Municipal Veterinarian'");
            $ensureCol('assistance_certificates', 'verified_species', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'verified_heads', "INT(11) NOT NULL DEFAULT 1");
            $ensureCol('assistance_certificates', 'verified_sex', "VARCHAR(50) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'verified_age', "VARCHAR(50) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'purpose', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'origin', "VARCHAR(255) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'destination', "VARCHAR(255) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'treatment_verification', "TEXT DEFAULT NULL");
            $ensureCol('assistance_certificates', 'value', "DECIMAL(12,2) DEFAULT 0.00");
            $ensureCol('assistance_certificates', 'transport_means', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'plate_number', "VARCHAR(50) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'shipper', "VARCHAR(255) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'shipper_address', "TEXT DEFAULT NULL");
            $ensureCol('assistance_certificates', 'or_number', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'amount_paid', "DECIMAL(10,2) DEFAULT 0.00");
            $ensureCol('assistance_certificates', 'payment_date', "DATE DEFAULT NULL");
            $ensureCol('assistance_certificates', 'status', "ENUM('Issued','Revoked') DEFAULT 'Issued'");
            $ensureCol('assistance_certificates', 'created_by', "INT(11) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'expiry_date', "DATE DEFAULT NULL");

            $ensureCol('farmer_assistance_requests', 'municipality', "VARCHAR(100) NOT NULL DEFAULT 'Ragay'");
            $ensureCol('farmer_assistance_requests', 'dest_barangay', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'dest_municipality', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'dest_address', "TEXT DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'transport_means', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'transport_means_other', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'plate_number', "VARCHAR(50) DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'shipper_name', "VARCHAR(255) DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'shipper_address', "TEXT DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'shipper_contact', "VARCHAR(50) DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'proposed_shipment_date', "DATE DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'transport_details', "TEXT DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'has_treatment', "ENUM('Yes','No') NOT NULL DEFAULT 'No'");
            $ensureCol('farmer_assistance_requests', 'treatment_history', "TEXT DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'attachment_url', "VARCHAR(500) DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'request_description', "TEXT DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'male_count', "INT(11) DEFAULT 0");
            $ensureCol('farmer_assistance_requests', 'female_count', "INT(11) DEFAULT 0");
            $ensureCol('farmer_assistance_requests', 'animal_species_other', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'purpose_other', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'estimated_value', "DECIMAL(12,2) DEFAULT 0.00");
            $ensureCol('farmer_assistance_requests', 'animal_description', "TEXT DEFAULT NULL");
            $ensureCol('farmer_assistance_requests', 'certified_accurate', "TINYINT(1) NOT NULL DEFAULT 1");

            $ensureCol('officer_profiles', 'municipality', "VARCHAR(255) DEFAULT NULL");
            $ensureCol('officer_profiles', 'assigned_region', "VARCHAR(255) DEFAULT NULL");
            $ensureCol('officer_profiles', 'province', "VARCHAR(255) DEFAULT NULL");
            $ensureCol('officer_profiles', 'office', "VARCHAR(255) DEFAULT NULL");
            $ensureCol('officer_profiles', 'position', "VARCHAR(255) DEFAULT NULL");
            $ensureCol('officer_profiles', 'department', "VARCHAR(255) DEFAULT NULL");
            $ensureCol('officer_profiles', 'gov_id', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('officer_profiles', 'profile_pix', "VARCHAR(255) DEFAULT NULL");

            // GPS accuracy & source metadata for farms and incidents
            $ensureCol('farms', 'gps_accuracy', "FLOAT NULL");
            $ensureCol('farms', 'gps_source', "VARCHAR(10) NULL");
            $ensureCol('incidents', 'gps_accuracy', "FLOAT NULL");
            $ensureCol('incidents', 'gps_source', "VARCHAR(10) NULL");
        } catch(Throwable $e) {
            error_log("Assistance tables check: " . $e->getMessage());
        }
    }
}
        ensure_assistance_tables($pdo);
    }
} catch(Throwable $e) { 
    error_log("Extended tables check: " . $e->getMessage()); 
}

$userId    = $_SESSION['user_id'] ?? 0;
$firstName = $_SESSION['firstName'] ?? 'Farmer';
$lastName  = $_SESSION['lastName']  ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// -------------------------------- EXPORT LIVESTOCK CSV (export_helper.php format) --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'GET' && isset($_GET['export']) && $_GET['export'] === 'livestock') {
    $expHelper = file_exists($baseDir . '/export_helper.php') ? $baseDir . '/export_helper.php' : (file_exists($baseDir . '/pages/export_helper.php') ? $baseDir . '/pages/export_helper.php' : __DIR__ . '/export_helper.php');
    if (file_exists($expHelper)) { require_once $expHelper; }
    
    $stExport = $pdo->prepare("
        SELECT l.*, f.name AS farm_name, f.address AS farm_address,
               u.firstName, u.lastName
        FROM livestock l
        INNER JOIN farms f ON l.farmId = f.id
        LEFT JOIN users u ON f.ownerId = u.id
        WHERE f.ownerId = ? AND f.status != 'Rejected'
        ORDER BY l.createdAt DESC, l.id DESC
    ");
    $stExport->execute([$userId]);
    $exportRows = $stExport->fetchAll(PDO::FETCH_ASSOC);

    $lgus = ['Cabusao', 'Del Gallego', 'Lupi', 'Ragay', 'Sipocot'];
    foreach ($exportRows as &$er) {
        $addr = $er['farm_address'] ?? '';
        $muni = '';
        foreach ($lgus as $lgu) {
            if (stripos($addr, $lgu) !== false) {
                $muni = $lgu;
                break;
            }
        }
        $er['municipality'] = $muni ?: 'Camarines Sur';
    }
    unset($er);

    $farmerFullName = trim($firstName . ' ' . $lastName);
    $_GET['format'] = $_GET['format'] ?? 'csv';
    agxReport('livestock', $exportRows, $farmerFullName);
    exit;
}

// -------------------------------- PROFILE UPDATE ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['update_profile'])) {
    header('Content-Type: application/json');
    try {
        $first = trim($_POST['firstName'] ?? '') ?: $firstName;
        $last  = trim($_POST['lastName']  ?? '') ?: $lastName;
        $email = trim($_POST['email']     ?? '');
        $mobile= trim($_POST['mobile']    ?? '');
        
        $pdo->prepare("UPDATE users SET firstName=?,lastName=?,email=?,mobile=? WHERE id=?")
            ->execute([$first,$last,$email,$mobile,$userId]);
        
        $_SESSION['firstName'] = $first;
        $_SESSION['lastName']  = $last;

        $picPath = null;
        if (!empty($_FILES['profile_pix']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['profile_pix']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                $picPath = "uploads/profiles/{$userId}_".time().".$ext";
                @mkdir(dirname($picPath), 0755, true);
                move_uploaded_file($_FILES['profile_pix']['tmp_name'], $picPath);
            }
        }
        
        $govId = trim($_POST['gov_id'] ?? '') ?: null;
        $gender = trim($_POST['gender'] ?? '') ?: null;
        $dob = trim($_POST['dob'] ?? '') ?: null;
        $addr = trim($_POST['address'] ?? '') ?: null;
        $emg = trim($_POST['emergency_contact'] ?? '') ?: null;

        $stmt = $pdo->prepare("INSERT INTO farmer_profiles(user_id,gov_id,gender,dob,address,emergency_contact" . ($picPath ? ",profile_pix" : "") . ")
            VALUES(?,?,?,?,?,?" . ($picPath ? ",?" : "") . ")
            ON DUPLICATE KEY UPDATE gov_id=VALUES(gov_id),gender=VALUES(gender),dob=VALUES(dob),address=VALUES(address),
            emergency_contact=VALUES(emergency_contact)" . ($picPath ? ",profile_pix=VALUES(profile_pix)" : ""));
        $params = [$userId, $govId, $gender, $dob, $addr, $emg];
        if ($picPath) $params[] = $picPath;
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
    } catch(Exception $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    header('Content-Type: application/json');
    try {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (strlen($new) < 6) throw new Exception('New password must be at least 6 characters.');
        if ($new !== $conf) throw new Exception('Passwords do not match.');
        
        $chk = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $chk->execute([$userId]);
        $currHash = $chk->fetchColumn();
        if ($currHash && !password_verify($cur, $currHash)) {
            throw new Exception('Current password is incorrect.');
        }
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$newHash, $userId]);
        echo json_encode(['success'=>true, 'message'=>'Password changed successfully!']);
    } catch(Exception $e){ echo json_encode(['success'=>false, 'message'=>$e->getMessage()]); }
    exit;
}

// -------------------------------- FARM REGISTRATION ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if (!function_exists('validate_district_gps')) {
    function validate_district_gps($rawLat, $rawLng): array {
        $hasLat = ($rawLat !== null && $rawLat !== '');
        $hasLng = ($rawLng !== null && $rawLng !== '');
        if (!$hasLat && !$hasLng) {
            return [null, null];
        }
        if (!$hasLat || !$hasLng || !is_numeric($rawLat) || !is_numeric($rawLng)) {
            throw new Exception('Invalid GPS coordinates. Please capture GPS or pin your location on the map.');
        }
        $lat = (float)$rawLat;
        $lng = (float)$rawLng;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new Exception('GPS coordinates are out of valid latitude/longitude range.');
        }
        if ($lat == 0.0 && $lng == 0.0) {
            throw new Exception('Invalid GPS coordinates (0, 0). Please pin your exact location on the map.');
        }
        if ($lat < 13.645 || $lat > 13.955 || $lng < 122.505 || $lng > 123.180) {
            throw new Exception('Location is outside Camarines Sur 1st District bounds (Lat 13.645-13.955, Lng 122.505-123.180). Please pin your exact farm/incident location on the map.');
        }
        return [$lat, $lng];
    }
}

if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_farm') {
    header('Content-Type: application/json');
    try {
        $name      = trim($_POST['farm_name']    ?? '');
        $address   = trim($_POST['farm_address'] ?? '');
        $type      = $_POST['farm_type']   ?? 'Mixed';
        $area      = (float)($_POST['farm_area'] ?? 0);
        [$lat, $lng] = validate_district_gps($_POST['farm_lat'] ?? null, $_POST['farm_lng'] ?? null);
        $gpsAcc    = (isset($_POST['gps_accuracy']) && $_POST['gps_accuracy'] !== '' && is_numeric($_POST['gps_accuracy'])) ? max(0.0, (float)$_POST['gps_accuracy']) : null;
        $rawSrc    = strtolower(trim($_POST['gps_source'] ?? ''));
        $gpsSource = in_array($rawSrc, ['gps', 'manual'], true) ? $rawSrc : ($lat !== null ? 'gps' : null);
        $region    = 'Region V   -   Bicol Region';
        $ownerName = trim($firstName.' '.$lastName);
        $petSurname  = trim($_POST['pet_surname']   ?? '');
        $petFirst    = trim($_POST['pet_firstname'] ?? '');
        $bizType   = $_POST['business_type'] ?? 'Sole Proprietor';
        $appType   = (isset($_POST['app_type']) && strtolower($_POST['app_type']) === 'renewal') ? 'Renewal' : 'New';
        $rawOwnership = strtolower(trim($_POST['ownership'] ?? 'private'));
        $ownership = ($rawOwnership === 'govt' || $rawOwnership === 'government') ? 'Govt' : 'Private';
        $rawTypes  = trim($_POST['facility_types'] ?? '');
        $facTypes  = $rawTypes ? json_encode(array_values(array_filter(array_map('trim', explode(',', $rawTypes))))) : json_encode([]);

        if (!$name) throw new Exception('Farm name is required.');
        if (!$petSurname || !$petFirst) throw new Exception('Petitioner name is required.');

        $photoPath = null;
        if (!empty($_POST['photo_data']) && strpos($_POST['photo_data'], 'base64,') !== false) {
            $parts = explode('base64,', $_POST['photo_data']);
            if (isset($parts[1])) {
                $imgData = base64_decode($parts[1]);
                if ($imgData !== false && strlen($imgData) > 0) {
                    $uploadDir = dirname(__DIR__) . '/uploads/farms/';
                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                    $fileName = 'farm_' . time() . '_' . $userId . '_' . bin2hex(random_bytes(4)) . '.jpg';
                    if (file_put_contents($uploadDir . $fileName, $imgData)) {
                        $photoPath = 'uploads/farms/' . $fileName;
                    }
                }
            }
        }

        // Check if this is a renewal of an existing farm registration
        $existingFarmId = !empty($_POST['renew_farm_id']) ? (int)$_POST['renew_farm_id'] : null;
        if ($appType === 'Renewal' && !$existingFarmId) {
            // Check if this user already has an existing farm matching this name
            $chk = $pdo->prepare("SELECT id FROM farms WHERE ownerId = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
            $chk->execute([$userId, $name]);
            $existingFarmId = (int)$chk->fetchColumn() ?: null;
        }

        $isRenewalUpdate = false;
        if ($appType === 'Renewal' && $existingFarmId) {
            $chkOwn = $pdo->prepare("SELECT id FROM farms WHERE id = ? AND ownerId = ?");
            $chkOwn->execute([$existingFarmId, $userId]);
            if ($chkOwn->fetchColumn()) {
                $isRenewalUpdate = true;
                $farmId = $existingFarmId;
            }
        }

        if ($isRenewalUpdate) {
            // UPDATE EXISTING FARM RECORD (lessens duplications of renewal registrations)
            $updateSql = "UPDATE farms SET 
                name = ?, ownerName = ?, type = ?, address = ?, latitude = ?, longitude = ?, gps_accuracy = ?, gps_source = ?, area = ?,
                status = 'Pending', application_type = 'Renewal', ownership = ?,
                petitioner_surname = ?, petitioner_firstname = ?, business_type = ?, facility_types = ?,
                " . ($photoPath ? "photo_url = ?, " : "") . "
                zip_code = '4410', region = ?, pet_ext = ?, pet_sex = ?, pet_house_no = ?, pet_street = ?,
                pet_barangay = ?, pet_municipality = ?, pet_province = ?, pet_dob = ?, pet_pob = ?,
                pet_civil_status = ?, pet_spouse = ?, pet_mothers_name = ?, pet_education = ?, pet_religion = ?,
                pet_male_members = ?, pet_female_members = ?, hh_head = ?, hh_head_name = ?, pwd = ?, fourps = ?,
                indigenous = ?, indigenous_specify = ?, has_gov_id = ?, gov_id_number = ?, farmers_assoc = ?,
                farmers_assoc_name = ?, emergency_contact_name = ?, emergency_contact_no = ?, main_livelihood = ?,
                farming_rice = ?, farming_corn = ?, farming_livestock = ?, farming_poultry = ?, farming_other = ?,
                farming_other_specify = ?, livestock_specify = ?, poultry_specify = ?, income_farming = ?,
                income_non_farming = ?, rejection_reason = NULL, approved_by = NULL, approved_at = NULL,
                rejected_by = NULL, rejected_at = NULL, updatedAt = CURRENT_TIMESTAMP
                WHERE id = ? AND ownerId = ?";

            $params = [
                $name, $ownerName, $type, $address, $lat, $lng, $gpsAcc, $gpsSource, $area,
                $ownership,
                $petSurname, $petFirst, $bizType, $facTypes
            ];
            if ($photoPath) $params[] = $photoPath;
            $params = array_merge($params, [
                $region, $_POST['pet_ext']??null, $_POST['pet_sex']??null, $_POST['pet_house_no']??null,
                $_POST['pet_street']??null, $_POST['pet_barangay']??null, $_POST['pet_municipality']??null,
                $_POST['pet_province']??'Camarines Sur', $_POST['pet_dob']??null, $_POST['pet_pob']??null,
                $_POST['pet_civil_status']??null, $_POST['pet_spouse']??null, $_POST['pet_mothers_name']??null,
                $_POST['pet_education']??null, $_POST['pet_religion']??null,
                (int)($_POST['pet_male_members']??0), (int)($_POST['pet_female_members']??0),
                $_POST['hh_head']??'No', $_POST['hh_head_name']??null,
                $_POST['pwd']??'No', $_POST['fourps']??'No',
                $_POST['indigenous']??'No', $_POST['indigenous_specify']??null,
                $_POST['has_gov_id']??'No', $_POST['gov_id_number']??null,
                $_POST['farmers_assoc']??'No', $_POST['farmers_assoc_name']??null,
                $_POST['emergency_contact_name']??null, $_POST['emergency_contact_no']??null,
                $_POST['main_livelihood']??'Farmer',
                isset($_POST['farming_rice'])?1:0, isset($_POST['farming_corn'])?1:0,
                isset($_POST['farming_livestock'])?1:0, isset($_POST['farming_poultry'])?1:0,
                isset($_POST['farming_other'])?1:0, $_POST['farming_other_specify']??null,
                $_POST['livestock_specify']??null, $_POST['poultry_specify']??null,
                (float)($_POST['income_farming']??0), (float)($_POST['income_non_farming']??0),
                $farmId, $userId
            ]);

            $pdo->prepare($updateSql)->execute($params);

            // Audit log
            $pdo->prepare("INSERT INTO audit_log (userId,action,tableName,recordId,details,ipAddress) VALUES (?,'UPDATE','farms',?,?,?)")
                ->execute([$userId, $farmId, json_encode(['farm_name'=>$name, 'application_type'=>'Renewal', 'action'=>'renewed_existing_farm']), $_SERVER['REMOTE_ADDR']??'']);

            // Update or generate renewal certificate
            $certNo = 'CERT-'.date('Y').'-'.str_pad($farmId, 5, '0', STR_PAD_LEFT);
            $issueDate = date('Y-m-d');
            $expiryDate = date('Y-m-d', strtotime('+1 year'));
            $exCert = $pdo->prepare("SELECT id FROM certificates WHERE farm_id=? ORDER BY id DESC LIMIT 1");
            $exCert->execute([$farmId]);
            $existingCertId = $exCert->fetchColumn();
            if ($existingCertId) {
                $pdo->prepare("UPDATE certificates SET cert_type='Renewal', issued_date=?, expiry_date=?, status='Active', notes='Renewed Farm Registration' WHERE id=?")
                    ->execute([$issueDate, $expiryDate, $existingCertId]);
            } else {
                $pdo->prepare("INSERT INTO certificates (farm_id,cert_number,cert_type,issued_by,issued_date,expiry_date,status,notes) VALUES (?,?,'Renewal','Bureau of Animal Industry - DA',?,?,'Active','Renewed Farm Registration')")
                    ->execute([$farmId, $certNo, $issueDate, $expiryDate]);
            }

            // Sync dynamic livestock rows
            $animalTypes = ['Cattle','Swine','Poultry','Goat','Carabao','Horse','Sheep','Rabbit'];
            foreach($animalTypes as $aType) {
                $qtyKey   = 'lv_qty_'.strtolower($aType);
                $tagKey   = 'lv_tag_'.strtolower($aType);
                $breedKey = 'lv_breed_'.strtolower($aType);
                $qty = (int)($_POST[$qtyKey] ?? 0);
                if ($qty > 0) {
                    $tag = trim($_POST[$tagKey] ?? '');
                    if (!$tag) $tag = 'TAG-'.strtoupper(substr($aType,0,3)).'-'.str_pad($farmId,4,'0',STR_PAD_LEFT);
                    $breed = trim($_POST[$breedKey] ?? '') ?: null;
                    $chkLv = $pdo->prepare("SELECT id FROM livestock WHERE farmId=? AND type=? LIMIT 1");
                    $chkLv->execute([$farmId, $aType]);
                    $existingLvId = $chkLv->fetchColumn();
                    if ($existingLvId) {
                        $pdo->prepare("UPDATE livestock SET qty=?, breed=COALESCE(?,breed), latitude=COALESCE(?,latitude), longitude=COALESCE(?,longitude) WHERE id=?")
                            ->execute([$qty, $breed, $lat, $lng, $existingLvId]);
                    } else {
                        $pdo->prepare("INSERT INTO livestock (farmId,type,tagId,breed,qty,healthStatus,vaccinationStatus,latitude,longitude) VALUES (?,?,?,?,?,'Healthy','None',?,?)")
                            ->execute([$farmId,$aType,$tag,$breed,$qty,$lat,$lng]);
                    }
                }
            }

            try {
                $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,farm_id,priority,is_read,created_at) VALUES(?,'INFO',?,?,?,'Low',0,NOW())")
                    ->execute([$userId, "[RENEWAL] Farm Renewal Submitted", "Your farm renewal application for '$name' was successfully submitted and existing farm record updated. Status is Pending DA review.", $farmId]);
            } catch(Exception $eN) {}

            // Sync with farm_registrations table
            try {
                $formDataJson = json_encode($_POST);
                $insFreg = $pdo->prepare("INSERT INTO farm_registrations (farmer_id, farm_id, farm_name, farm_address, form_data, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
                $insFreg->execute([$userId, $farmId, $name, $address, $formDataJson]);
            } catch(Exception $eReg) {}

            echo json_encode(['success'=>true, 'farm_id'=>$farmId, 'message'=>"Farm '$name' renewal submitted! Existing registration record updated."]);

        } else {
            // INSERT NEW FARM RECORD
            $pdo->prepare("INSERT INTO farms (name,ownerId,ownerName,type,address,latitude,longitude,gps_accuracy,gps_source,area,status,application_type,ownership,
                petitioner_surname,petitioner_firstname,business_type,facility_types,photo_url,zip_code,region,
                pet_ext,pet_sex,pet_house_no,pet_street,pet_barangay,pet_municipality,pet_province,
                pet_dob,pet_pob,pet_civil_status,pet_spouse,pet_mothers_name,pet_education,pet_religion,
                pet_male_members,pet_female_members,hh_head,hh_head_name,pwd,fourps,indigenous,indigenous_specify,
                has_gov_id,gov_id_number,farmers_assoc,farmers_assoc_name,emergency_contact_name,emergency_contact_no,
                main_livelihood,farming_rice,farming_corn,farming_livestock,farming_poultry,farming_other,
                farming_other_specify,livestock_specify,poultry_specify,income_farming,income_non_farming)
                VALUES (?,?,?,?,?,?,?,?,?,?,'Pending',?,?, ?,?,?,?,?,'4410',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $name,$userId,$ownerName,$type,$address,$lat,$lng,$gpsAcc,$gpsSource,$area,$appType,$ownership,
                    $petSurname,$petFirst,$bizType,$facTypes,$photoPath,$region,
                    $_POST['pet_ext']??null,$_POST['pet_sex']??null,$_POST['pet_house_no']??null,
                    $_POST['pet_street']??null,$_POST['pet_barangay']??null,$_POST['pet_municipality']??null,
                    $_POST['pet_province']??'Camarines Sur',$_POST['pet_dob']??null,$_POST['pet_pob']??null,
                    $_POST['pet_civil_status']??null,$_POST['pet_spouse']??null,$_POST['pet_mothers_name']??null,
                    $_POST['pet_education']??null,$_POST['pet_religion']??null,
                    (int)($_POST['pet_male_members']??0),(int)($_POST['pet_female_members']??0),
                    $_POST['hh_head']??'No',$_POST['hh_head_name']??null,
                    $_POST['pwd']??'No',$_POST['fourps']??'No',
                    $_POST['indigenous']??'No',$_POST['indigenous_specify']??null,
                    $_POST['has_gov_id']??'No',$_POST['gov_id_number']??null,
                    $_POST['farmers_assoc']??'No',$_POST['farmers_assoc_name']??null,
                    $_POST['emergency_contact_name']??null,$_POST['emergency_contact_no']??null,
                    $_POST['main_livelihood']??'Farmer',
                    isset($_POST['farming_rice'])?1:0,isset($_POST['farming_corn'])?1:0,
                    isset($_POST['farming_livestock'])?1:0,isset($_POST['farming_poultry'])?1:0,
                    isset($_POST['farming_other'])?1:0,$_POST['farming_other_specify']??null,
                    $_POST['livestock_specify']??null,$_POST['poultry_specify']??null,
                    (float)($_POST['income_farming']??0),(float)($_POST['income_non_farming']??0)
                ]);
            $farmId = (int)$pdo->lastInsertId();

            // Insert per-type livestock counts from dynamic rows
            $animalTypes = ['Cattle','Swine','Poultry','Goat','Carabao','Horse','Sheep','Rabbit'];
            foreach($animalTypes as $aType) {
                $qtyKey   = 'lv_qty_'.strtolower($aType);
                $tagKey   = 'lv_tag_'.strtolower($aType);
                $breedKey = 'lv_breed_'.strtolower($aType);
                $qty = (int)($_POST[$qtyKey] ?? 0);
                if ($qty > 0) {
                    $tag = trim($_POST[$tagKey] ?? '');
                    if (!$tag) $tag = 'TAG-'.strtoupper(substr($aType,0,3)).'-'.str_pad($farmId,4,'0',STR_PAD_LEFT);
                    $breed = trim($_POST[$breedKey] ?? '') ?: null;
                    $pdo->prepare("INSERT INTO livestock (farmId,type,tagId,breed,qty,healthStatus,vaccinationStatus,latitude,longitude) VALUES (?,?,?,?,?,'Healthy','None',?,?)")
                        ->execute([$farmId,$aType,$tag,$breed,$qty,$lat,$lng]);
                }
            }

            $pdo->prepare("INSERT INTO audit_log (userId,action,tableName,recordId,details,ipAddress) VALUES (?,'CREATE','farms',?,?,?)")
                ->execute([$userId,$farmId,json_encode(['farm_name'=>$name,'application_type'=>'New']),$_SERVER['REMOTE_ADDR']??'']);

            $certNo = 'CERT-'.date('Y').'-'.str_pad($farmId,5,'0',STR_PAD_LEFT);
            $issueDate = date('Y-m-d');
            $expiryDate = date('Y-m-d', strtotime('+1 year'));
            $pdo->prepare("INSERT INTO certificates (farm_id,cert_number,cert_type,issued_by,issued_date,expiry_date,status,notes) VALUES (?,?,'Registration','Bureau of Animal Industry - DA',?,?,'Active','Auto-issued Farm Registration')")
                ->execute([$farmId,$certNo,$issueDate,$expiryDate]);

            try {
                $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,farm_id,priority,is_read,created_at) VALUES(?,'INFO',?,?,?,'Low',0,NOW())")
                    ->execute([$userId, "[FARM] Farm Application Submitted", "Your farm application for '$name' was successfully submitted and certificate $certNo has been generated.", $farmId]);
            } catch(Exception $eN) {}

            // Sync with farm_registrations table
            try {
                $formDataJson = json_encode($_POST);
                $insFreg = $pdo->prepare("INSERT INTO farm_registrations (farmer_id, farm_id, farm_name, farm_address, form_data, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
                $insFreg->execute([$userId, $farmId, $name, $address, $formDataJson]);
            } catch(Exception $eReg) {}

            echo json_encode(['success'=>true,'farm_id'=>$farmId,'message'=>"Farm '$name' registered successfully!"]);
        }
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// -------------------------------- LIVESTOCK UPDATE ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_livestock') {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['livestock_id'];
        $notes = trim($_POST['notes'] ?? '') ?: null;
        $farmingType = in_array($_POST['farming_type'] ?? '', ['Backyard', 'Commercial'], true) ? $_POST['farming_type'] : 'Backyard';
        try {
            $pdo->prepare("UPDATE livestock SET type=?,farming_type=?,breed=?,age=?,weight=?,healthStatus=?,vaccinationStatus=?,qty=?,tagId=?,notes=? WHERE id=?")
                ->execute([$_POST['type'],$farmingType,$_POST['breed']??null,$_POST['age']??null,$_POST['weight']??null,$_POST['healthStatus'],$_POST['vaccinationStatus'],$_POST['qty'],$_POST['tagId'],$notes,$id]);
        } catch(Throwable $e) {
            $pdo->prepare("UPDATE livestock SET type=?,breed=?,age=?,weight=?,healthStatus=?,vaccinationStatus=?,qty=?,tagId=?,notes=? WHERE id=?")
                ->execute([$_POST['type'],$_POST['breed']??null,$_POST['age']??null,$_POST['weight']??null,$_POST['healthStatus'],$_POST['vaccinationStatus'],$_POST['qty'],$_POST['tagId'],$notes,$id]);
        }
        echo json_encode(['success'=>true]);
    } catch(Exception $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

// -------------------------------- ADD LIVESTOCK ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_livestock') {
    header('Content-Type: application/json');
    try {
        $farmId = (int)$_POST['farm_id'];
        $f=$pdo->prepare("SELECT id FROM farms WHERE id=? AND ownerId=?");
        $f->execute([$farmId,$userId]);
        if(!$f->fetch()) throw new Exception('Farm not found');
        $health = $_POST['healthStatus'] ?? 'Healthy';
        $vax = $_POST['vaccinationStatus'] ?? 'None';
        $farmingType = in_array($_POST['farming_type'] ?? '', ['Backyard', 'Commercial'], true) ? $_POST['farming_type'] : 'Backyard';
        $notes = trim($_POST['notes'] ?? '') ?: null;
        $tagId = trim($_POST['tagId'] ?? '');
        if (!$tagId) throw new Exception('Tag ID / Physical Tag Number is required.');

        try {
            $pdo->prepare("INSERT INTO livestock(farmId,type,farming_type,tagId,breed,age,weight,qty,healthStatus,vaccinationStatus,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$farmId,$_POST['type'],$farmingType,$tagId,$_POST['breed']??null,$_POST['age']??null,$_POST['weight']??null,$_POST['qty']??1,$health,$vax,$notes]);
        } catch(Throwable $dbErr) {
            // Fallback if column not yet added
            $pdo->prepare("INSERT INTO livestock(farmId,type,tagId,breed,age,weight,qty,healthStatus,vaccinationStatus,notes) VALUES(?,?,?,?,?,?,?,?,?,?)")
                ->execute([$farmId,$_POST['type'],$tagId,$_POST['breed']??null,$_POST['age']??null,$_POST['weight']??null,$_POST['qty']??1,$health,$vax,$notes]);
        }
        echo json_encode(['success'=>true]);
    } catch(Exception $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

// -------------------------------- DELETE LIVESTOCK ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_livestock') {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['livestock_id'];
        $stmt=$pdo->prepare("SELECT l.tagId,l.farmId FROM livestock l JOIN farms f ON l.farmId=f.id WHERE l.id=? AND f.ownerId=?");
        $stmt->execute([$id,$userId]);
        if(!$stmt->fetch()) throw new Exception('Unauthorized or not found');
        $pdo->prepare("DELETE FROM livestock WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]);
    } catch(Exception $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

// -------------------------------- INCIDENT REPORT --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report_incident') {
    header('Content-Type: application/json');
    try {
        $allowedTypes = ['Sick', 'Dead', 'Stray', 'Disease', 'Others'];
        $rawType  = $_POST['incident_type'] ?? 'Others';
        $type     = in_array($rawType, $allowedTypes, true) ? $rawType : 'Others';
        $title    = trim($_POST['title']       ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $farmId   = !empty($_POST['farm_id'])   ? (int)$_POST['farm_id']   : null;
        [$lat, $lng] = validate_district_gps($_POST['latitude'] ?? null, $_POST['longitude'] ?? null);
        $gpsAcc    = (isset($_POST['gps_accuracy']) && $_POST['gps_accuracy'] !== '' && is_numeric($_POST['gps_accuracy'])) ? max(0.0, (float)$_POST['gps_accuracy']) : null;
        $rawSrc    = strtolower(trim($_POST['gps_source'] ?? ''));
        $gpsSource = in_array($rawSrc, ['gps', 'manual'], true) ? $rawSrc : ($lat !== null ? 'gps' : null);
        $symptoms = trim($_POST['symptoms'] ?? '');
        $severity = $_POST['severity'] ?? 'Medium';
        $lvType   = !empty($_POST['livestock_type']) ? trim($_POST['livestock_type']) : null;

        if (!$title || !$desc) {
            throw new Exception('Title and description are required.');
        }

        $disease_keywords = ['ASF','FMD','Avian Flu','African Swine Fever','Foot and Mouth','Bird Flu','PRRS','Classical'];
        $priority = in_array($severity, ['Low', 'Medium', 'High', 'Critical'], true) ? $severity : 'Medium';
        $searchCorpus = strtolower($title . ' ' . $desc . ' ' . $symptoms);
        foreach ($disease_keywords as $kw) {
            if (strpos($searchCorpus, strtolower($kw)) !== false) {
                $priority = 'Critical';
                break;
            }
        }

        $photoPath = null;
        if (!empty($_POST['photo_data']) && strpos($_POST['photo_data'], 'base64,') !== false) {
            $parts = explode('base64,', $_POST['photo_data']);
            if (isset($parts[1])) {
                $imgData = base64_decode($parts[1]);
                if ($imgData !== false && strlen($imgData) > 0) {
                    $uploadDir = dirname(__DIR__) . '/uploads/incidents/';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }
                    $fileName = 'inc_' . time() . '_' . $userId . '_' . bin2hex(random_bytes(4)) . '.jpg';
                    if (file_put_contents($uploadDir . $fileName, $imgData)) {
                        $photoPath = 'uploads/incidents/' . $fileName;
                    }
                }
            }
        }

        // Try insert with extended columns + GPS metadata, fallback to basic if columns missing
        try {
            $pdo->prepare("INSERT INTO incidents(farmId,reporterId,type,title,description,latitude,longitude,gps_accuracy,gps_source,photoUrl,status,priority,livestock_type,symptoms,severity) VALUES(?,?,?,?,?,?,?,?,?,?,'Pending',?,?,?,?)")
                ->execute([$farmId,$userId,$type,$title,$desc,$lat,$lng,$gpsAcc,$gpsSource,$photoPath,$priority,$lvType,$symptoms,$severity]);
        } catch(PDOException $e2) {
            $fallbackDesc = $desc;
            if ($lvType) $fallbackDesc .= "\n[Livestock: $lvType]";
            if ($symptoms) $fallbackDesc .= "\n[Symptoms: $symptoms]";
            $pdo->prepare("INSERT INTO incidents(farmId,reporterId,type,title,description,latitude,longitude,photoUrl,status,priority) VALUES(?,?,?,?,?,?,?,?,'Pending',?)")
                ->execute([$farmId,$userId,$type,$title,$fallbackDesc,$lat,$lng,$photoPath,$priority]);
        }
        $incId = (int)$pdo->lastInsertId();

        $officers = $pdo->query("SELECT id FROM users WHERE role='Agriculture Official' AND status='Active'")->fetchAll();
        foreach($officers as $officer) {
            $alertTitle = $priority==='Critical' ? "[CRITICAL ALERT] Disease Alert" : "[ALERT] Incident Report #$incId";
            $alertMsg   = "Farmer ".htmlspecialchars($firstName.' '.$lastName)." reported: $title. Type: $type. Priority: $priority.";
            try {
                $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,incident_id,priority,created_at) VALUES(?,'CRITICAL_ALERT',?,?,?,?,NOW())")
                    ->execute([$officer['id'],$alertTitle,$alertMsg,$incId,$priority]);
            } catch(PDOException $e3) {}
        }

        // Notify reporting farmer
        try {
            $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,incident_id,priority,is_read,created_at) VALUES(?,'INFO',?,?,?,'Medium',0,NOW())")
                ->execute([$userId, "[INCIDENT] Incident Report #$incId Submitted", "Your incident report \"$title\" has been submitted. DA Field Operations officers have been alerted.", $incId]);
        } catch(Exception $eN) {}

        // Broadcast critical alerts to all other farmers
        if ($priority === 'Critical') {
            try {
                $otherFarmers = $pdo->prepare("SELECT id FROM users WHERE role='Farmer' AND status='Active' AND id != ?");
                $otherFarmers->execute([$userId]);
                $fNotifStmt = $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,incident_id,priority,is_read,created_at) VALUES(?,'OUTBREAK_WARNING',?,?,?,'Critical',0,NOW())");
                foreach ($otherFarmers->fetchAll() as $of) {
                    $fNotifStmt->execute([
                        $of['id'],
                        "[CRITICAL ALERT] ALERT: Disease Outbreak Reported",
                        "Urgent biosecurity advisory: A critical disease report ($type - $title) was flagged in your area. Enforce strict quarantine protocols immediately.",
                        $incId
                    ]);
                }
            } catch(Exception $eCrit) {}
        }

        // Log to audit
        try {
            $pdo->prepare("INSERT INTO audit_log(userId,action,tableName,recordId,details,ipAddress) VALUES(?,'CREATE','incidents',?,?,?)")
                ->execute([$userId,$incId,json_encode(['title'=>$title,'type'=>$type,'priority'=>$priority]),$_SERVER['REMOTE_ADDR']??'']);
        } catch(Exception $e4){}

        echo json_encode(['success'=>true,'incident_id'=>$incId,'priority'=>$priority,'notified'=>count($officers).' officers']);
    } catch(Exception $e){
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// -------------------------------- PUBLIC REPORT SUBMIT ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'public_report') {
    header('Content-Type: application/json');
    try {
        $reportType  = $_POST['report_type']   ?? 'Others';
        $otherType   = trim($_POST['other_type']   ?? '');
        $description = trim($_POST['description']  ?? '');
        $phone       = trim($_POST['contact_phone'] ?? '');
        $email       = trim($_POST['contact_email'] ?? '');
        $lat         = !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : null;
        $lng         = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

        if(!$description) throw new Exception('Description is required.');
        if(!$phone) throw new Exception('Contact phone is required.');

        $idPhoto   = null;
        $facePhoto = null;

        if(!empty($_POST['id_photo']) && strpos($_POST['id_photo'],'base64,')!==false){
            $d=base64_decode(explode(',',$_POST['id_photo'])[1]);
            @mkdir('uploads/public_reports',0755,true);
            $idPhoto='uploads/public_reports/id_'.time().'.jpg';
            file_put_contents($idPhoto,$d);
        }
        if(!empty($_POST['face_photo']) && strpos($_POST['face_photo'],'base64,')!==false){
            $d=base64_decode(explode(',',$_POST['face_photo'])[1]);
            @mkdir('uploads/public_reports',0755,true);
            $facePhoto='uploads/public_reports/face_'.time().'.jpg';
            file_put_contents($facePhoto,$d);
        }

        // Enforce upload requirement: Citizen/Farmer must provide an ID photo or supporting file
        $hasUpload = $idPhoto || $facePhoto || (!empty($_FILES['id_photo']['name']) && $_FILES['id_photo']['error'] === UPLOAD_ERR_OK) || (!empty($_FILES['media_file']['name']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK);
        if (!$hasUpload) {
            throw new Exception('Upload required: Please upload an ID photo or supporting file before submitting.');
        }

        $pdo->prepare("INSERT INTO public_reports(reportType,otherType,description,contactPhone,contactEmail,idPhotoUrl,facePhotoUrl,latitude,longitude,status,createdAt)
            VALUES(?,?,?,?,?,?,?,?,?,'Pending',NOW())")
            ->execute([$reportType,$otherType,$description,$phone,$email,$idPhoto,$facePhoto,$lat,$lng]);

        $rId = $pdo->lastInsertId();

        // Notify DA officers
        $officers = $pdo->query("SELECT id FROM users WHERE role='Agriculture Official' AND status='Active'")->fetchAll();
        foreach($officers as $officer){
            try{
                $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,priority,created_at) VALUES(?,'WARNING',?,?,'Medium',NOW())")
                    ->execute([$officer['id'],"[REPORT] Public Report #$rId","Public report submitted: ".substr($description,0,80)]);
            }catch(Exception $ne){}
        }

        echo json_encode(['success'=>true,'report_id'=>$rId]);
    } catch(Exception $e){
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// -------------------------------- WEBRTC SIGNAL ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'webrtc_signal') {
    header('Content-Type: application/json');
    try {
        $toId    = (int)($_POST['to_id'] ?? 0);
        $payload = $_POST['payload'] ?? '';
        if(!$toId || !$payload) throw new Exception('Missing fields');
        $pdo->prepare("INSERT INTO webrtc_signals(sender_id,receiver_id,payload,consumed) VALUES(?,?,?,0)")
            ->execute([$userId,$toId,$payload]);
        echo json_encode(['success'=>true]);
    } catch(Exception $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

if ($requestMethod === 'GET' && isset($_GET['action']) && $_GET['action'] === 'webrtc_poll') {
    header('Content-Type: application/json');
    $signals = $pdo->prepare("SELECT id,sender_id,payload FROM webrtc_signals WHERE receiver_id=? AND consumed=0 ORDER BY created_at ASC LIMIT 10");
    $signals->execute([$userId]);
    $rows = $signals->fetchAll();
    if(!empty($rows)){
        $ids = implode(',', array_column($rows,'id'));
        $pdo->query("UPDATE webrtc_signals SET consumed=1 WHERE id IN($ids)");
    }
    echo json_encode(['success'=>true,'signals'=>$rows]);
    exit;
}

// -------------------------------- NOTIFICATIONS ACTIONS ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_notif_read') {
    header('Content-Type: application/json');
    $notifId = (int)($_POST['notif_id'] ?? 0);
    if ($notifId > 0) {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$notifId, $userId]);
    }
    $stUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stUnread->execute([$userId]);
    $rem = (int)$stUnread->fetchColumn();
    echo json_encode(['success' => true, 'unread_count' => $rem]);
    exit;
}

if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_notifs_read') {
    header('Content-Type: application/json');
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
    echo json_encode(['success' => true, 'unread_count' => 0]);
    exit;
}

if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_notif') {
    header('Content-Type: application/json');
    $notifId = (int)($_POST['notif_id'] ?? 0);
    if ($notifId > 0) {
        $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([$notifId, $userId]);
    }
    $stUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stUnread->execute([$userId]);
    $rem = (int)$stUnread->fetchColumn();

    $stTotal = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=?");
    $stTotal->execute([$userId]);
    $total = (int)$stTotal->fetchColumn();

    echo json_encode(['success' => true, 'unread_count' => $rem, 'total_count' => $total]);
    exit;
}

if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all_notifs') {
    header('Content-Type: application/json');
    $pdo->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$userId]);
    echo json_encode(['success' => true, 'unread_count' => 0, 'total_count' => 0]);
    exit;
}

if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_test_notif') {
    header('Content-Type: application/json');
    $testTemplates = [
        [
            'type' => 'CRITICAL_ALERT',
            'title' => '[ALERT] Outbreak Warning: ASF Active Buffer Zone',
            'message' => 'African Swine Fever active buffer zone established within 7km. Enforce mandatory footbaths, disinfect vehicles, and prohibit swill feeding immediately.',
            'priority' => 'Critical'
        ],
        [
            'type' => 'FARM_APPROVED',
            'title' => '[APPROVED] Farm Registration Approved',
            'message' => 'Good news! Your farm facility inspection has been completed and your registration is officially verified & approved by DA officials.',
            'priority' => 'Medium'
        ],
        [
            'type' => 'WARNING',
            'title' => '<i class="bi bi-exclamation-triangle-fill"></i> Mandatory Livestock Deworming & Vaccination',
            'message' => 'DA Municipal technicians will conduct free Anthrax and Hemorrhagic Septicemia vaccinations in your sector next Wednesday. Please prepare logbooks.',
            'priority' => 'High'
        ],
        [
            'type' => 'INFO',
            'title' => '[CERTIFICATE] Digital Certificate Issued',
            'message' => 'Farm registration and digital credentials are active and verified in your Digital Certificates tab.',
            'priority' => 'Low'
        ]
    ];
    $chosen = $testTemplates[array_rand($testTemplates)];
    $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,priority,is_read,created_at) VALUES(?,?,?,?,?,0,NOW())")
        ->execute([$userId, $chosen['type'], $chosen['title'], $chosen['message'], $chosen['priority']]);
    $newId = (int)$pdo->lastInsertId();
    $stUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stUnread->execute([$userId]);
    $rem = (int)$stUnread->fetchColumn();
    echo json_encode([
        'success' => true,
        'notif' => [
            'id' => $newId,
            'type' => $chosen['type'],
            'title' => $chosen['title'],
            'message' => $chosen['message'],
            'priority' => $chosen['priority'],
            'created_at' => date('M d, Y H:i')
        ],
        'unread_count' => $rem
    ]);
    exit;
}

if ($requestMethod === 'GET' && isset($_GET['action']) && $_GET['action'] === 'poll_notifications') {
    header('Content-Type: application/json');
    $stU = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stU->execute([$userId]);
    $uCount = (int)$stU->fetchColumn();

    $stN = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 15");
    $stN->execute([$userId]);
    $list = $stN->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'unread_count' => $uCount,
        'notifications' => array_map(function($n) {
            return [
                'id' => (int)$n['id'],
                'type' => $n['type'],
                'title' => $n['title'],
                'message' => $n['message'],
                'priority' => $n['priority'],
                'is_read' => (int)$n['is_read'],
                'created_at' => date('M d, Y H:i', strtotime($n['created_at']))
            ];
        }, $list)
    ]);
    exit;
}

// -------------------------------- LIVESTOCK HEALTH HISTORY (FETCH) ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_animal_health_history') {
    header('Content-Type: application/json');
    try {
        $lvId = (int)($_POST['livestock_id'] ?? 0);
        $st = $pdo->prepare("
            SELECT hr.*, l.tagId, l.type as animal_type, l.breed, l.healthStatus as currentHealth, f.name as farm_name
            FROM livestock l
            JOIN farms f ON l.farmId = f.id
            LEFT JOIN livestock_health_records hr ON l.id = hr.livestock_id
            WHERE l.id = ? AND f.ownerId = ?
            ORDER BY hr.checkup_date DESC, hr.id DESC
        ");
        $st->execute([$lvId, $userId]);
        $rows = $st->fetchAll();
        $records = [];
        $animalInfo = null;
        foreach ($rows as $r) {
            if (!$animalInfo) {
                $animalInfo = [
                    'id' => $lvId,
                    'tagId' => $r['tagId'],
                    'type' => $r['animal_type'],
                    'breed' => $r['breed'],
                    'currentHealth' => $r['currentHealth'],
                    'farm_name' => $r['farm_name']
                ];
            }
            if (!empty($r['id'])) {
                $records[] = $r;
            }
        }
        echo json_encode(['success' => true, 'animal' => $animalInfo, 'records' => $records]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------- LIVESTOCK HEALTH RECORD (ADD) ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_health_record') {
    header('Content-Type: application/json');
    try {
        $lvId = (int)($_POST['livestock_id'] ?? 0);
        $chk = $pdo->prepare("SELECT l.id, l.farmId FROM livestock l JOIN farms f ON l.farmId=f.id WHERE l.id=? AND f.ownerId=?");
        $chk->execute([$lvId, $userId]);
        $lvRow = $chk->fetch();
        if (!$lvRow) throw new Exception('Livestock not found or access denied.');

        $recType = $_POST['record_type'] ?? 'Health Check';
        $cond = $_POST['health_condition'] ?? 'Healthy';
        $treatment = trim($_POST['treatment_details'] ?? '') ?: null;
        $med = trim($_POST['medicine_administered'] ?? '') ?: null;
        $dosage = trim($_POST['dosage'] ?? '') ?: null;
        $adminBy = trim($_POST['administered_by'] ?? '') ?: 'Farmer / Attending Vet';
        $checkDate = !empty($_POST['checkup_date']) ? $_POST['checkup_date'] : date('Y-m-d');
        $nextDate = !empty($_POST['next_checkup_date']) ? $_POST['next_checkup_date'] : null;
        $notes = trim($_POST['notes'] ?? '') ?: null;

        $pdo->prepare("INSERT INTO livestock_health_records(livestock_id,farm_id,record_type,health_condition,treatment_details,medicine_administered,dosage,administered_by,checkup_date,next_checkup_date,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$lvId, $lvRow['farmId'], $recType, $cond, $treatment, $med, $dosage, $adminBy, $checkDate, $nextDate, $notes]);

        // Update livestock condition & checkup dates
        $updateCols = "healthStatus = ?, lastCheckup = ?";
        $updateParams = [$cond, $checkDate];
        if ($recType === 'Vaccination') {
            $updateCols .= ", vaccinationStatus = 'Complete'";
        } elseif ($recType === 'Deworming') {
            $updateCols .= ", dewormingStatus = 'Up to Date'";
        }
        if ($nextDate) {
            $updateCols .= ", nextCheckup = ?";
            $updateParams[] = $nextDate;
        }
        $updateParams[] = $lvId;
        $pdo->prepare("UPDATE livestock SET $updateCols WHERE id = ?")->execute($updateParams);

        echo json_encode(['success' => true, 'message' => 'Animal health record logged successfully!']);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------- UNIFIED FARMER ASSISTANCE REQUEST (VET & SLAUGHTER) --------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_assistance_request') {
    header('Content-Type: application/json');
    try {
        $assistType = trim($_POST['assistance_type'] ?? 'Veterinary Assistance');
        if (!in_array($assistType, ['Veterinary Assistance', 'Slaughter Assistance'], true)) {
            $assistType = 'Veterinary Assistance';
        }

        // Section A: Farmer Information
        $fName = trim($_POST['farmer_name'] ?? '') ?: trim($firstName . ' ' . $lastName);
        $fContact = trim($_POST['farmer_contact'] ?? '') ?: ($profileData['mobile'] ?? '');
        $fAddr = trim($_POST['farmer_address'] ?? '') ?: ($profileData['address'] ?? '');
        $fBrgy = trim($_POST['farmer_barangay'] ?? '') ?: ($fBarangay ?: ($primaryFarm['pet_barangay'] ?? ''));
        $fMuni = trim($_POST['farmer_municipality'] ?? '') ?: ($farmerMuni ?: 'Ragay');

        // Section C: Livestock Information
        $species = trim($_POST['animal_species'] ?? 'Swine');
        $speciesOther = trim($_POST['animal_species_other'] ?? '') ?: null;
        $headCount = max(1, (int)($_POST['head_count'] ?? 1));
        $sex = trim($_POST['sex'] ?? 'Mixed');
        if (!in_array($sex, ['Male', 'Female', 'Mixed'], true)) $sex = 'Mixed';
        $maleCount = ($sex === 'Mixed') ? (int)($_POST['male_count'] ?? 0) : ($sex === 'Male' ? $headCount : 0);
        $femaleCount = ($sex === 'Mixed') ? (int)($_POST['female_count'] ?? 0) : ($sex === 'Female' ? $headCount : 0);
        $age = trim($_POST['animal_age'] ?? '') ?: 'Adult';
        $purpose = trim($_POST['purpose'] ?? ($assistType === 'Slaughter Assistance' ? 'Slaughter' : 'Clinical Care'));
        $purposeOther = trim($_POST['purpose_other'] ?? '') ?: null;
        $estValue = (float)($_POST['estimated_value'] ?? 0);
        $animalDesc = trim($_POST['animal_description'] ?? '') ?: null;

        // Section D: Origin and Destination
        $originBrgy = trim($_POST['origin_barangay'] ?? '') ?: $fBrgy;
        $originMuni = trim($_POST['origin_municipality'] ?? '') ?: $fMuni;
        $destBrgy = trim($_POST['dest_barangay'] ?? '') ?: null;
        $destMuni = trim($_POST['dest_municipality'] ?? '') ?: null;
        $destAddr = trim($_POST['dest_address'] ?? '') ?: null;

        // Section E: Transportation Information
        $transMeans = trim($_POST['transport_means'] ?? '') ?: null;
        $transOther = trim($_POST['transport_means_other'] ?? '') ?: null;
        $plateNum = trim($_POST['plate_number'] ?? '') ?: null;
        $shipperName = trim($_POST['shipper_name'] ?? '') ?: $fName;
        $shipperAddr = trim($_POST['shipper_address'] ?? '') ?: $fAddr;
        $shipperContact = trim($_POST['shipper_contact'] ?? '') ?: $fContact;
        $shipDate = !empty($_POST['proposed_shipment_date']) ? $_POST['proposed_shipment_date'] : date('Y-m-d');
        $transDetails = trim($_POST['transport_details'] ?? '') ?: null;

        // Section F: Treatment / Medication History
        $hasTreatment = (trim($_POST['has_treatment'] ?? 'No') === 'Yes') ? 'Yes' : 'No';
        $treatHistory = null;
        if ($hasTreatment === 'Yes' && !empty($_POST['treatment_history'])) {
            $treatHistory = is_array($_POST['treatment_history']) ? json_encode($_POST['treatment_history']) : trim($_POST['treatment_history']);
        }

        // Section G: Supporting Documents
        $attachUrl = null;
        if (!empty($_FILES['supporting_doc']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['supporting_doc']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','pdf','webp'])) {
                $dir = 'uploads/assistance_docs/';
                @mkdir($dir, 0755, true);
                $fname = "assist_{$userId}_" . time() . ".$ext";
                if (move_uploaded_file($_FILES['supporting_doc']['tmp_name'], $dir . $fname)) {
                    $attachUrl = $dir . $fname;
                }
            }
        }

        // Section H: Request Description & Declaration
        $reqDesc = trim($_POST['request_description'] ?? '');
        if (!$reqDesc) throw new Exception('Please provide a description of your request or concern.');
        $certified = !empty($_POST['certified_accurate']) ? 1 : 1;

        // Generate Reference Number
        $refNo = 'REQ-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

                // Ensure farmer_assistance_requests table has all required columns before insert
        ensure_assistance_tables($pdo);
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `farmer_assistance_requests`")->fetchAll(PDO::FETCH_COLUMN);
            $cSet = array_flip($cols);
            
            if (!isset($cSet['request_description'])) {
                $pdo->exec("ALTER TABLE `farmer_assistance_requests` ADD COLUMN `request_description` TEXT NULL");
                if (isset($cSet['description'])) {
                    $pdo->exec("UPDATE `farmer_assistance_requests` SET `request_description` = `description` WHERE `request_description` IS NULL");
                }
            }
            if (!isset($cSet['animal_species_other'])) {
                $pdo->exec("ALTER TABLE `farmer_assistance_requests` ADD COLUMN `animal_species_other` VARCHAR(100) DEFAULT NULL");
            }
            if (!isset($cSet['male_count'])) {
                $pdo->exec("ALTER TABLE `farmer_assistance_requests` ADD COLUMN `male_count` INT(11) DEFAULT 0");
            }
            if (!isset($cSet['female_count'])) {
                $pdo->exec("ALTER TABLE `farmer_assistance_requests` ADD COLUMN `female_count` INT(11) DEFAULT 0");
            }
            if (!isset($cSet['purpose_other'])) {
                $pdo->exec("ALTER TABLE `farmer_assistance_requests` ADD COLUMN `purpose_other` VARCHAR(100) DEFAULT NULL");
            }
            if (!isset($cSet['transport_means_other'])) {
                $pdo->exec("ALTER TABLE `farmer_assistance_requests` ADD COLUMN `transport_means_other` VARCHAR(100) DEFAULT NULL");
            }
            if (!isset($cSet['has_treatment'])) {
                $pdo->exec("ALTER TABLE `farmer_assistance_requests` ADD COLUMN `has_treatment` ENUM('Yes','No') NOT NULL DEFAULT 'No'");
            }
            if (!isset($cSet['treatment_history'])) {
                $pdo->exec("ALTER TABLE `farmer_assistance_requests` ADD COLUMN `treatment_history` TEXT DEFAULT NULL");
            }
            if (!isset($cSet['attachment_url'])) {
                $pdo->exec("ALTER TABLE `farmer_assistance_requests` ADD COLUMN `attachment_url` VARCHAR(500) DEFAULT NULL");
            }
            if (!isset($cSet['certified_accurate'])) {
                $pdo->exec("ALTER TABLE `farmer_assistance_requests` ADD COLUMN `certified_accurate` TINYINT(1) NOT NULL DEFAULT 1");
            }
            if (!isset($cSet['municipality'])) {
                $pdo->exec("ALTER TABLE `farmer_assistance_requests` ADD COLUMN `municipality` VARCHAR(100) NOT NULL DEFAULT 'Ragay'");
            }
        } catch (Throwable $eMig) {}

        $stmt = $pdo->prepare("INSERT INTO farmer_assistance_requests (
            reference_no, farmer_id, assistance_type, status, municipality,
            farmer_name, farmer_contact, farmer_address, farmer_barangay, farmer_municipality,
            animal_species, animal_species_other, head_count, sex, male_count, female_count,
            animal_age, purpose, purpose_other, estimated_value, animal_description,
            origin_barangay, origin_municipality, dest_barangay, dest_municipality, dest_address,
            transport_means, transport_means_other, plate_number, shipper_name, shipper_address,
            shipper_contact, proposed_shipment_date, transport_details,
            has_treatment, treatment_history, attachment_url, request_description, certified_accurate, created_at
        ) VALUES (?, ?, ?, 'Request Submitted', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $stmt->execute([
            $refNo, $userId, $assistType, $originMuni,
            $fName, $fContact, $fAddr, $fBrgy, $fMuni,
            $species, $speciesOther, $headCount, $sex, $maleCount, $femaleCount,
            $age, $purpose, $purposeOther, $estValue, $animalDesc,
            $originBrgy, $originMuni, $destBrgy, $destMuni, $destAddr,
            $transMeans, $transOther, $plateNum, $shipperName, $shipperAddr,
            $shipperContact, $shipDate, $transDetails,
            $hasTreatment, $treatHistory, $attachUrl, $reqDesc, $certified
        ]);
        $reqId = (int)$pdo->lastInsertId();

        // Also synchronize with legacy tables for existing count stats & backwards compatibility
        $farmId = !empty($farmsData[0]['id']) ? (int)$farmsData[0]['id'] : 0;
        if ($farmId === 0) {
            $fChk = $pdo->prepare("SELECT id FROM farms WHERE ownerId = ? LIMIT 1");
            $fChk->execute([$userId]);
            $farmId = (int)$fChk->fetchColumn();
        }

        if ($assistType === 'Veterinary Assistance') {
            try {
                if ($farmId > 0) {
                    $pdo->prepare("INSERT INTO veterinary_requests(request_code,farmer_id,farm_id,request_type,urgency,livestock_type,animal_count,preferred_date,description,attachment_url,status) VALUES(?,?,?,'Veterinary consultation','Normal',?,?,?,?,?,'Pending')")
                        ->execute([$refNo, $userId, $farmId, $species, $headCount, $shipDate, $reqDesc, $attachUrl]);
                }
            } catch (Throwable $eLegacy) {}
        } else {
            try {
                if ($farmId > 0) {
                    $pdo->prepare("INSERT INTO slaughter_declarations(declaration_no,farmer_id,farm_id,animal_tag_id,livestock_type,slaughter_type,purpose,slaughter_date,location,document_url,status) VALUES(?,?,?,?,?,'Slaughterhouse slaughter',?,?,?,?,'Pending Review')")
                        ->execute([$refNo, $userId, $farmId, $refNo, $species, $purpose, $shipDate, ($destAddr ?: ($destMuni ?: $originMuni)), $attachUrl]);
                }
            } catch (Throwable $eLegacy) {}
        }

        // Notify DA Officials
        try {
            $officers = $pdo->query("SELECT id FROM users WHERE role='Agriculture Official' AND status='Active'")->fetchAll();
            $notifStmt = $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,priority,created_at) VALUES(?,'INFO',?,?,'Normal',NOW())");
            foreach ($officers as $off) {
                $notifStmt->execute([$off['id'], "[ASSISTANCE] New {$assistType} ({$refNo})", "Farmer {$fName} requested {$assistType} for {$headCount} {$species} ({$originMuni}). Reference: {$refNo}"]);
            }
        } catch(Throwable $eN) {}

        echo json_encode([
            'success' => true,
            'reference_no' => $refNo,
            'request_id' => $reqId,
            'message' => "Assistance request {$refNo} submitted successfully! Your submission has been forwarded to the Official Portal for inspection and official certificate preparation."
        ]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_farmer_assistance_details') {
    header('Content-Type: application/json');
    try {
        $reqId = (int)($_POST['request_id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM farmer_assistance_requests WHERE id = ? AND farmer_id = ?");
        $st->execute([$reqId, $userId]);
        $req = $st->fetch(PDO::FETCH_ASSOC);
        if (!$req) throw new Exception('Request not found.');

        $stC = $pdo->prepare("SELECT * FROM assistance_certificates WHERE request_id = ? ORDER BY id DESC");
        $stC->execute([$reqId]);
        $certs = $stC->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'request' => $req, 'certificates' => $certs]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------- REQUEST VETERINARY ASSISTANCE ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_veterinary_assistance') {
    header('Content-Type: application/json');
    try {
        $farmId = (int)($_POST['farm_id'] ?? 0);
        $fchk = $pdo->prepare("SELECT id FROM farms WHERE id=? AND ownerId=?");
        $fchk->execute([$farmId, $userId]);
        if (!$fchk->fetch()) throw new Exception('Please select a valid registered farm.');

        $reqType = trim($_POST['request_type'] ?? '');
        $validTypes = ['Veterinary consultation','Emergency animal treatment','Vaccination','Deworming','Farm inspection','Breeding assistance'];
        if (!in_array($reqType, $validTypes, true)) throw new Exception('Invalid assistance type requested.');

        $urgency = in_array($_POST['urgency'] ?? '', ['Normal','Urgent','Emergency'], true) ? $_POST['urgency'] : 'Normal';
        $lvType = trim($_POST['livestock_type'] ?? '') ?: null;
        $count = max(1, (int)($_POST['animal_count'] ?? 1));
        $prefDate = !empty($_POST['preferred_date']) ? $_POST['preferred_date'] : null;
        $desc = trim($_POST['description'] ?? '');
        if (empty($desc)) throw new Exception('Please describe the assistance needed or animal condition.');

        $attachUrl = null;
        if (!empty($_FILES['attachment']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','pdf'])) {
                $dir = 'uploads/vet_requests/';
                @mkdir($dir, 0755, true);
                $fname = "vet_{$userId}_" . time() . ".$ext";
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . $fname)) {
                    $attachUrl = $dir . $fname;
                }
            }
        }

        $reqCode = 'VET-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $pdo->prepare("INSERT INTO veterinary_requests(request_code,farmer_id,farm_id,request_type,urgency,livestock_type,animal_count,preferred_date,description,attachment_url,status) VALUES(?,?,?,?,?,?,?,?,?,?,'Pending')")
            ->execute([$reqCode, $userId, $farmId, $reqType, $urgency, $lvType, $count, $prefDate, $desc, $attachUrl]);

        // Notify active DA Agriculture Officials
        try {
            $officers = $pdo->query("SELECT id FROM users WHERE role='Agriculture Official' AND status='Active'")->fetchAll();
            $notifStmt = $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,priority,created_at) VALUES(?,'WARNING',?,?,'High',NOW())");
            foreach ($officers as $off) {
                $notifStmt->execute([$off['id'], "[VET] New Veterinary Request ({$reqCode})", "Farmer requested {$reqType} ({$urgency}) for {$count} {$lvType}."]);
            }
        } catch(Exception $eN) {}

        echo json_encode(['success' => true, 'code' => $reqCode, 'message' => "Assistance request {$reqCode} submitted successfully! Our DA veterinarian/technician will follow up."]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------- LIVESTOCK INSURANCE ASSISTANCE (PCIC) ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_insurance_request') {
    header('Content-Type: application/json');
    try {
        $farmId = (int)($_POST['farm_id'] ?? 0);
        $fchk = $pdo->prepare("SELECT id FROM farms WHERE id=? AND ownerId=?");
        $fchk->execute([$farmId, $userId]);
        if (!$fchk->fetch()) throw new Exception('Please select a valid farm.');

        $reqType = trim($_POST['insurance_type'] ?? 'Insurance Application');
        $lvType = trim($_POST['livestock_type'] ?? '') ?: 'Mixed';
        $headCount = max(1, (int)($_POST['head_count'] ?? HeadCount(1)));
        $causeDeath = trim($_POST['cause_of_death'] ?? '') ?: null;
        $incDate = !empty($_POST['date_of_incident']) ? $_POST['date_of_incident'] : null;
        $loss = (float)($_POST['estimated_loss'] ?? 0);

        $docUrl = null;
        if (!empty($_FILES['document_file']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','jpg','jpeg','png'])) {
                $dir = 'uploads/insurance_docs/';
                @mkdir($dir, 0755, true);
                $fname = "ins_doc_{$userId}_" . time() . ".$ext";
                if (move_uploaded_file($_FILES['document_file']['tmp_name'], $dir . $fname)) {
                    $docUrl = $dir . $fname;
                }
            }
        }

        $photoUrl = null;
        if (!empty($_FILES['photo_file']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $dir = 'uploads/insurance_photos/';
                @mkdir($dir, 0755, true);
                $fname = "ins_pic_{$userId}_" . time() . ".$ext";
                if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $dir . $fname)) {
                    $photoUrl = $dir . $fname;
                }
            }
        }

        $refNo = 'PCIC-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $pdo->prepare("INSERT INTO insurance_assistance(reference_no,farmer_id,farm_id,request_type,livestock_type,head_count,cause_of_death,date_of_incident,estimated_loss,document_url,photo_url,pcic_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,'Pending Verification')")
            ->execute([$refNo, $userId, $farmId, $reqType, $lvType, $headCount, $causeDeath, $incDate, $loss, $docUrl, $photoUrl]);

        echo json_encode(['success' => true, 'reference_no' => $refNo, 'message' => "PCIC insurance submission {$refNo} recorded and submitted to DA for endorsement!"]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------- SLAUGHTER DECLARATION --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_slaughter_declaration') {
    header('Content-Type: application/json');
    try {
        $farmId = (int)($_POST['farm_id'] ?? 0);
        $fchk = $pdo->prepare("SELECT id FROM farms WHERE id=? AND ownerId=?");
        $fchk->execute([$farmId, $userId]);
        if (!$fchk->fetch()) throw new Exception('Please select a valid farm.');

        $tagId = trim($_POST['animal_tag_id'] ?? '');
        $lvType = trim($_POST['livestock_type'] ?? '');
        $slaughterType = trim($_POST['slaughter_type'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $sDate = !empty($_POST['slaughter_date']) ? $_POST['slaughter_date'] : date('Y-m-d');
        $loc = trim($_POST['location'] ?? '');
        if (!$tagId || !$purpose || !$loc) throw new Exception('Animal Tag ID, Purpose, and Location are required.');

        $docUrl = null;
        if (!empty($_FILES['supporting_doc']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['supporting_doc']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','jpg','jpeg','png'])) {
                $dir = 'uploads/slaughter_docs/';
                @mkdir($dir, 0755, true);
                $fname = "slg_{$userId}_" . time() . ".$ext";
                if (move_uploaded_file($_FILES['supporting_doc']['tmp_name'], $dir . $fname)) {
                    $docUrl = $dir . $fname;
                }
            }
        }

        $declNo = 'SLG-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $pdo->prepare("INSERT INTO slaughter_declarations(declaration_no,farmer_id,farm_id,animal_tag_id,livestock_type,slaughter_type,purpose,slaughter_date,location,document_url,status) VALUES(?,?,?,?,?,?,?,?,?,?,'Pending Review')")
            ->execute([$declNo, $userId, $farmId, $tagId, $lvType, $slaughterType, $purpose, $sDate, $loc, $docUrl]);

        echo json_encode(['success' => true, 'declaration_no' => $declNo, 'message' => "Slaughter activity declaration {$declNo} recorded for regulatory inspection!"]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------- DIGITAL CERTIFICATE UPLOAD ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_digital_certificate') {
    header('Content-Type: application/json');
    try {
        $farmId = (int)($_POST['farm_id'] ?? 0);
        $certType = trim($_POST['cert_type'] ?? 'Ownership Certificate');
        $title = trim($_POST['document_title'] ?? '') ?: $certType;
        $certNo = trim($_POST['cert_number'] ?? '') ?: ('CERT-' . date('Ymd') . '-' . rand(1000, 9999));
        $issuedBy = trim($_POST['issued_by'] ?? 'Municipal Agriculture Office');
        $issuedDate = !empty($_POST['issued_date']) ? $_POST['issued_date'] : date('Y-m-d');
        $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

        $fileUrl = null;
        if (!empty($_FILES['cert_file']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['cert_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','jpg','jpeg','png','webp'])) {
                $dir = 'uploads/certificates/';
                @mkdir($dir, 0755, true);
                $fname = "cert_{$userId}_" . time() . ".$ext";
                if (move_uploaded_file($_FILES['cert_file']['tmp_name'], $dir . $fname)) {
                    $fileUrl = $dir . $fname;
                }
            }
        }
        if (!$fileUrl) throw new Exception('Please attach the certificate document (PDF or image).');

        $pdo->prepare("INSERT INTO certificates(farm_id,farmer_id,cert_number,document_title,cert_type,issued_by,issued_date,expiry_date,file_url,uploaded_by,status) VALUES(?,?,?,?,?,?,?,?,?,'Farmer','Active')")
            ->execute([$farmId, $userId, $certNo, $title, $certType, $issuedBy, $issuedDate, $expiryDate, $fileUrl]);

        echo json_encode(['success' => true, 'message' => "Digital certificate '$title' successfully uploaded and logged!"]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------- RSBSA ENROLLMENT FORM -------------------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_rsbsa_form') {
    header('Content-Type: application/json');
    try {
        $refNo = trim($_POST['reference_no'] ?? '') ?: ('05-20-' . date('y') . '-' . str_pad($userId, 5, '0', STR_PAD_LEFT));
        $sName = trim($_POST['surname'] ?? '');
        $fNameIn = trim($_POST['first_name'] ?? '');
        $mNameIn = trim($_POST['middle_name'] ?? '');
        $applicantName = trim("$sName, $fNameIn $mNameIn");
        if (!$applicantName || $applicantName === ',') {
            $applicantName = trim($firstName . ' ' . $lastName);
        }
        $brgy = trim($_POST['pet_barangay'] ?? $_POST['barangay'] ?? '');
        $muni = trim($_POST['pet_municipality'] ?? $_POST['municipality'] ?? 'Camarines Sur');
        $livelihood = 'Farmer';
        if (!empty($_POST['farming_worker'])) $livelihood = 'Farmworker';
        if (!empty($_POST['farming_fisher'])) $livelihood = 'Fisherfolk';
        if (!empty($_POST['farming_youth'])) $livelihood = 'Agri Youth';
        $formDataJson = json_encode($_POST);

        $stmt = $pdo->prepare("INSERT INTO rsbsa_enrollments (farmer_id, reference_no, applicant_name, barangay, municipality, livelihood_category, form_data, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$userId, $refNo, $applicantName, $brgy, $muni, $livelihood, $formDataJson]);
        $enrollmentId = (int)$pdo->lastInsertId();

        // Notify officers
        try {
            $officers = $pdo->query("SELECT id FROM users WHERE role='Agriculture Official' AND status='Active'")->fetchAll();
            foreach ($officers as $off) {
                $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, priority, is_read, created_at) VALUES (?, 'INFO', 'New RSBSA Enrollment Submitted', ?, 'Normal', 0, NOW())")
                    ->execute([$off['id'], "Farmer $applicantName submitted an RSBSA Enrollment application (Ref: $refNo)."]);
            }
            $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, priority, is_read, created_at) VALUES (?, 'INFO', 'RSBSA Enrollment Submitted', ?, 'Normal', 0, NOW())")
                ->execute([$userId, "Your RSBSA Enrollment application ($refNo) has been submitted successfully and is pending verification."]);
        } catch(Exception $eNotif) {}

        echo json_encode(['success' => true, 'id' => $enrollmentId, 'message' => "Your RSBSA Enrollment Form ($refNo) was successfully submitted for DA verification!"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to save RSBSA form: ' . $e->getMessage()]);
    }
    exit;
}

// -------------------------------- PCIC LIVESTOCK INSURANCE APPLICATION -------------------------------------------------------------
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_pcic_form') {
    header('Content-Type: application/json');
    try {
        $appNo = trim($_POST['application_no'] ?? '') ?: ('PCIC-' . date('Ymd') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)));
        $lNameIn = trim($_POST['last_name'] ?? '');
        $fNameIn = trim($_POST['first_name'] ?? '');
        $mNameIn = trim($_POST['middle_name'] ?? '');
        $applicantName = trim("$lNameIn, $fNameIn $mNameIn");
        if (!$applicantName || $applicantName === ',') {
            $applicantName = trim($firstName . ' ' . $lastName);
        }
        $farmLoc = trim($_POST['farm_location'] ?? $_POST['applicant_address'] ?? 'Camarines Sur');
        $animType = trim($_POST['animal_type'] ?? 'Mixed Livestock');
        $headCount = (int)($_POST['head_count'] ?? 1);
        if ($headCount < 1) $headCount = 1;
        $formDataJson = json_encode($_POST);

        $stmt = $pdo->prepare("INSERT INTO pcic_insurance_applications (farmer_id, application_no, applicant_name, farm_location, animal_type, head_count, form_data, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$userId, $appNo, $applicantName, $farmLoc, $animType, $headCount, $formDataJson]);
        $appId = (int)$pdo->lastInsertId();

        // Notify officers
        try {
            $officers = $pdo->query("SELECT id FROM users WHERE role='Agriculture Official' AND status='Active'")->fetchAll();
            foreach ($officers as $off) {
                $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, priority, is_read, created_at) VALUES (?, 'INFO', 'New PCIC Insurance Application', ?, 'Normal', 0, NOW())")
                    ->execute([$off['id'], "Farmer $applicantName submitted a PCIC Livestock Insurance application (App #: $appNo, $headCount heads)."]);
            }
            $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, priority, is_read, created_at) VALUES (?, 'INFO', 'PCIC Insurance Application Submitted', ?, 'Normal', 0, NOW())")
                ->execute([$userId, "Your PCIC Livestock Insurance Application ($appNo) has been submitted successfully and is pending review."]);
        } catch(Exception $eNotif) {}

        echo json_encode(['success' => true, 'id' => $appId, 'message' => "Your PCIC Livestock Mortality Insurance application ($appNo) was successfully submitted!"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to save PCIC form: ' . $e->getMessage()]);
    }
    exit;
}

// -------------------------------- FETCH ALL DATA ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
$stFarms = $pdo->prepare("SELECT * FROM farms WHERE ownerId=? ORDER BY createdAt DESC");
$stFarms->execute([$userId]);
$farmsData = $stFarms->fetchAll();

// Per-animal-type totals for dashboard
$stLvByType = $pdo->prepare("
    SELECT l.type, COALESCE(SUM(l.qty),0) as total
    FROM livestock l JOIN farms f ON l.farmId=f.id
    WHERE f.ownerId=?
    GROUP BY l.type
    ORDER BY total DESC
");
$stLvByType->execute([$userId]);
$lvByType = $stLvByType->fetchAll(PDO::FETCH_KEY_PAIR);
$totalLivestock = array_sum($lvByType);

$stInc = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reporterId=? AND status IN('Pending','In Progress')");
$stInc->execute([$userId]);
$activeIncidents = (int)$stInc->fetchColumn();

$stPend = $pdo->prepare("SELECT COUNT(*) FROM farms WHERE ownerId=? AND status='Pending'");
$stPend->execute([$userId]);
$pendingFarms = (int)$stPend->fetchColumn();

$stAllLv = $pdo->prepare("SELECT l.*,f.name as farmName,f.type as farmType,f.status as farmStatus,f.address as farmAddress,f.id as farmId
    FROM farms f LEFT JOIN livestock l ON l.farmId=f.id
    WHERE f.ownerId=? AND f.status!='Rejected'
    ORDER BY f.name ASC, l.createdAt DESC");
$stAllLv->execute([$userId]);
$allLvRows = $stAllLv->fetchAll();

$livestockByFarm = [];
foreach($allLvRows as $row) {
    $fid = $row['farmId'];
    if(!isset($livestockByFarm[$fid])) {
        $livestockByFarm[$fid] = [
            'farm' => ['id'=>$fid,'name'=>$row['farmName'],'type'=>$row['farmType'],'status'=>$row['farmStatus'],'address'=>$row['farmAddress']],
            'livestock' => []
        ];
    }
    if(!empty($row['tagId'])) $livestockByFarm[$fid]['livestock'][] = $row;
}

$stIncs = $pdo->prepare("SELECT i.*,f.name as farmName FROM incidents i LEFT JOIN farms f ON i.farmId=f.id WHERE i.reporterId=? ORDER BY i.createdAt DESC LIMIT 30");
$stIncs->execute([$userId]);
$incidentsData = $stIncs->fetchAll();

$stProf = $pdo->prepare("SELECT u.*,fp.gov_id,fp.gender,fp.dob,fp.address,fp.emergency_contact,fp.profile_pix FROM users u LEFT JOIN farmer_profiles fp ON u.id=fp.user_id WHERE u.id=?");
$stProf->execute([$userId]);
$profileData = $stProf->fetch();
if(!$profileData) $profileData = ['firstName'=>$firstName,'lastName'=>$lastName,'email'=>'','mobile'=>''];
$myPixUrl  = user_pix_url($profileData['profile_pix'] ?? null);
$myInitial = strtoupper(substr($firstName,0,1));

foreach($farmsData as $farm) {
    $ex = $pdo->prepare("SELECT id FROM certificates WHERE farm_id=?");
    $ex->execute([$farm['id']]);
    if(!$ex->fetch()) {
        $certNo = 'CERT-'.date('Y').'-'.str_pad($farm['id'],5,'0',STR_PAD_LEFT);
        $issueDate = $farm['createdAt'] ? date('Y-m-d',strtotime($farm['createdAt'])) : date('Y-m-d');
        $expiryDate = date('Y-m-d',strtotime($issueDate.'+1 year'));
        try {
            $pdo->prepare("INSERT INTO certificates(farm_id,cert_number,cert_type,issued_by,issued_date,expiry_date,status,notes) VALUES(?,?,'Registration','Bureau of Animal Industry - DA',?,?,'Active','Auto-issued Farm Registration')")
                ->execute([$farm['id'],$certNo,$issueDate,$expiryDate]);
        } catch(Exception $e) {}
    }
}

$stCerts = $pdo->prepare("
    SELECT c.*,f.name as farm_name,f.type as farm_type,f.address as farm_address,f.area as farm_area,
           f.ownerName,f.region,f.zip_code,f.status as farm_status,
           u.firstName as owner_firstname,u.lastName as owner_lastname
    FROM certificates c
    JOIN farms f ON c.farm_id=f.id
    LEFT JOIN users u ON f.ownerId=u.id
    WHERE f.ownerId=?
    ORDER BY c.created_at DESC
");
$stCerts->execute([$userId]);
$certsData = $stCerts->fetchAll();

$stUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$stUnread->execute([$userId]);
$unreadCount = (int)$stUnread->fetchColumn();

$stNotifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
$stNotifs->execute([$userId]);
$notificationsData = $stNotifs->fetchAll();

// Status badges and profile data for Registration & Official Forms
$farmRegStatus = null;
$rsbsaStatus = null;
$pcicStatus = null;
$latestFarmReg = null;
$latestRsbsa = null;
$latestPcic = null;

try {
    $st = $pdo->prepare("SELECT * FROM farm_registrations WHERE farmer_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$userId]);
    $latestFarmReg = $st->fetch(PDO::FETCH_ASSOC);
    if ($latestFarmReg) {
        $farmRegStatus = strtolower($latestFarmReg['status']);
    }
} catch(Exception $e) {}

try {
    $st = $pdo->prepare("SELECT * FROM rsbsa_enrollments WHERE farmer_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$userId]);
    $latestRsbsa = $st->fetch(PDO::FETCH_ASSOC);
    if ($latestRsbsa) {
        $rsbsaStatus = strtolower($latestRsbsa['status']);
    }
} catch(Exception $e) {}

try {
    $st = $pdo->prepare("SELECT * FROM pcic_insurance_applications WHERE farmer_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$userId]);
    $latestPcic = $st->fetch(PDO::FETCH_ASSOC);
    if ($latestPcic) {
        $pcicStatus = strtolower($latestPcic['status']);
    }
} catch(Exception $e) {}

$primaryFarm = $farmsData[0] ?? [];
$fName = $profileData['firstName'] ?? $firstName;
$lName = $profileData['lastName'] ?? $lastName;
$mName = $profileData['middleName'] ?? ($primaryFarm['pet_mothers_name'] ?? '');
$fFullName = trim($fName . ' ' . $lName);
$fMobile = $profileData['mobile'] ?? '';
$fEmail = $profileData['email'] ?? '';
$fDob = $profileData['dob'] ?? ($primaryFarm['pet_dob'] ?? '');
$fGender = $profileData['gender'] ?? ($primaryFarm['pet_sex'] ?? 'Male');
$fGovId = $profileData['gov_id'] ?? ($primaryFarm['gov_id_number'] ?? '');
$fAddress = $profileData['address'] ?? ($primaryFarm['address'] ?? '');
$fBarangay = $primaryFarm['pet_barangay'] ?? '';
$fMunicipality = $primaryFarm['pet_municipality'] ?? '';
$fProvince = $primaryFarm['pet_province'] ?? 'Camarines Sur';
$fCivilStatus = $primaryFarm['pet_civil_status'] ?? 'Single';
$fEmergencyName = $profileData['emergency_contact'] ?? ($primaryFarm['emergency_contact_name'] ?? '');
$fEmergencyPhone = $primaryFarm['emergency_contact_no'] ?? '';

// -------------------------------- RESOLVE FARMER MUNICIPALITY ---------------------------------------------
$lgus = ['Ragay', 'Sipocot', 'Lupi', 'Del Gallego', 'Cabusao'];
$farmerMuni = '';
if (!empty($farmsData)) {
    foreach ($farmsData as $f) {
        $pm = trim($f['pet_municipality'] ?? '');
        foreach ($lgus as $lgu) {
            if (strcasecmp($pm, $lgu) === 0) {
                $farmerMuni = $lgu;
                break 2;
            }
        }
    }
}
if (!$farmerMuni && !empty($farmsData)) {
    foreach ($farmsData as $f) {
        $addr = $f['address'] ?? '';
        foreach ($lgus as $lgu) {
            if (stripos($addr, $lgu) !== false) {
                $farmerMuni = $lgu;
                break 2;
            }
        }
    }
}
if (!$farmerMuni) {
    $profAddr = trim(($profileData['address'] ?? '') . ' ' . ($primaryFarm['address'] ?? ''));
    foreach ($lgus as $lgu) {
        if (stripos($profAddr, $lgu) !== false) {
            $farmerMuni = $lgu;
            break;
        }
    }
}
if (!$farmerMuni) {
    try {
        $stM = $pdo->prepare("SELECT municipality FROM rsbsa_enrollments WHERE farmer_id = ? AND municipality IS NOT NULL ORDER BY id DESC LIMIT 1");
        $stM->execute([$userId]);
        $rMuni = $stM->fetchColumn() ?: '';
        foreach ($lgus as $lgu) {
            if (stripos($rMuni, $lgu) !== false) {
                $farmerMuni = $lgu;
                break;
            }
        }
    } catch(Throwable $e){}
}
if (!$farmerMuni) {
    $farmerMuni = 'Ragay';
}

// Calculate farmer's spatial statistics for this municipality
$farmerMuniFarms = 0;
$farmerMuniLivestock = 0;
foreach ($farmsData as $f) {
    $fm = $f['pet_municipality'] ?? '';
    $fa = $f['address'] ?? '';
    if (strcasecmp($fm, $farmerMuni) === 0 || stripos($fa, $farmerMuni) !== false) {
        $farmerMuniFarms++;
        $farmerMuniLivestock += (int)($f['total_livestock'] ?? 0);
    }
}
if ($farmerMuniFarms === 0) {
    $farmerMuniFarms = count($farmsData);
    $farmerMuniLivestock = (int)($totalLivestock ?? 0);
}

// -------------------------------- EXTENDED MODULES DATA --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
$stVet = $pdo->prepare("SELECT vr.*, f.name as farm_name FROM veterinary_requests vr LEFT JOIN farms f ON vr.farm_id=f.id WHERE vr.farmer_id=? ORDER BY vr.created_at DESC");
$stVet->execute([$userId]);
$vetRequests = $stVet->fetchAll();

$stIns = $pdo->prepare("SELECT ia.*, f.name as farm_name FROM insurance_assistance ia LEFT JOIN farms f ON ia.farm_id=f.id WHERE ia.farmer_id=? ORDER BY ia.created_at DESC");
$stIns->execute([$userId]);
$insuranceRecords = $stIns->fetchAll();

$stSlg = $pdo->prepare("SELECT sd.*, f.name as farm_name FROM slaughter_declarations sd LEFT JOIN farms f ON sd.farm_id=f.id WHERE sd.farmer_id=? ORDER BY sd.created_at DESC");
$stSlg->execute([$userId]);
$slaughterRecords = $stSlg->fetchAll();

$assistanceRequests = [];
$myAssistanceCerts = [];
try {
    $stAssistance = $pdo->prepare("
        SELECT ar.*,
               (SELECT COUNT(*) FROM assistance_certificates WHERE request_id = ar.id AND cert_type = 'VHC') as has_vhc,
               (SELECT COUNT(*) FROM assistance_certificates WHERE request_id = ar.id AND cert_type = 'COI') as has_coi
        FROM farmer_assistance_requests ar
        WHERE ar.farmer_id = ?
        ORDER BY ar.created_at DESC
    ");
    $stAssistance->execute([$userId]);
    $assistanceRequests = $stAssistance->fetchAll(PDO::FETCH_ASSOC);

    $stAssistanceCerts = $pdo->prepare("
        SELECT ac.*,
               ar.reference_no as req_ref, ar.farmer_name, ar.farmer_address, ar.farmer_barangay,
               ar.farmer_municipality, ar.farmer_contact, ar.assistance_type,
               ar.animal_species, ar.head_count, ar.sex, ar.animal_age, ar.purpose as req_purpose,
               ar.proposed_shipment_date, ar.estimated_value, ar.transport_means as req_transport_means,
               ar.plate_number as req_plate_number, ar.shipper_name, ar.shipper_address as req_shipper_address,
               ar.origin_barangay, ar.origin_municipality, ar.dest_address, ar.dest_barangay, ar.dest_municipality,
               ar.treatment_history
        FROM assistance_certificates ac
        LEFT JOIN farmer_assistance_requests ar ON ac.request_id = ar.id
        WHERE ac.farmer_id = ?
        ORDER BY ac.created_at DESC
    ");
    $stAssistanceCerts->execute([$userId]);
    $myAssistanceCerts = $stAssistanceCerts->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $eAss) {
    error_log("Farmer assistance data query: " . $eAss->getMessage());
}

$stAllCerts = $pdo->prepare("
    SELECT c.*, f.name as farm_name 
    FROM certificates c 
    LEFT JOIN farms f ON c.farm_id=f.id 
    WHERE c.farmer_id=? OR f.ownerId=? 
    ORDER BY c.created_at DESC
");
$stAllCerts->execute([$userId, $userId]);
$digitalCertificates = $stAllCerts->fetchAll();

// Animal icons & emoji map
$animalEmoji = [
    'Cattle'  => '🐄',
    'Swine'   => '🐖',
    'Poultry' => '🐓',
    'Goat'    => '🐐',
    'Carabao' => '🐃',
    'Horse'   => '🐴',
    'Sheep'   => '🐑',
    'Rabbit'  => '🐇',
    'Duck'    => '🦆',
    'Turkey'  => '🦃',
    'Other'   => '🐾'
];
$allAnimalTypes = ['Swine', 'Poultry', 'Carabao', 'Cattle', 'Goat', 'Sheep'];

function healthBadge($s){$map=['Healthy'=>'badge-healthy','Sick'=>'badge-sick','Recovered'=>'badge-approved','Deceased'=>'badge-pending'];return $map[$s]??'badge-pending';}
function statusBadge($s){$map=['Pending'=>'badge-pending','Approved'=>'badge-approved','Rejected'=>'badge-rejected','Active'=>'badge-approved','Resolved'=>'badge-approved','In Progress'=>'badge-pending','Closed'=>'badge-pending'];return $map[$s]??'badge-pending';}
function animalIcon($type){$map=['Cattle'=>'bi-shield-shaded','Swine'=>'bi-heart-pulse-fill','Poultry'=>'bi-feather','Goat'=>'bi-geo-fill','Carabao'=>'bi-water','Horse'=>'bi-lightning-charge-fill','Sheep'=>'bi-cloud-fill','Rabbit'=>'bi-stars','Duck'=>'bi-water','Turkey'=>'bi-egg-fill'];return $map[$type]??'bi-tag-fill';}
function getAnimalTheme($type){
    $themes = [
        'Swine'   => ['bg'=>'linear-gradient(135deg,#fdf2f8,#fce7f3)','color'=>'#db2777'],
        'Cattle'  => ['bg'=>'linear-gradient(135deg,#fffbeb,#fef3c7)','color'=>'#d97706'],
        'Poultry' => ['bg'=>'linear-gradient(135deg,#fff7ed,#ffedd5)','color'=>'#ea580c'],
        'Goat'    => ['bg'=>'linear-gradient(135deg,#ecfdf5,#d1fae5)','color'=>'#059669'],
        'Carabao' => ['bg'=>'linear-gradient(135deg,#f8fafc,#e2e8f0)','color'=>'#334155'],
        'Horse'   => ['bg'=>'linear-gradient(135deg,#f5f3ff,#ede9fe)','color'=>'#7c3aed'],
        'Sheep'   => ['bg'=>'linear-gradient(135deg,#f8fafc,#f1f5f9)','color'=>'#475569'],
        'Rabbit'  => ['bg'=>'linear-gradient(135deg,#fdf4ff,#fae8ff)','color'=>'#c026d3'],
    ];
    return $themes[$type] ?? ['bg'=>'linear-gradient(135deg,#ecfdf5,#d1fae5)','color'=>'#059669'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>AgriTrace+ | Farmer Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/exifr/dist/lite.umd.js"></script>
<?php $googleMapsApiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : ''; ?>
<?php if (!empty($googleMapsApiKey)): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($googleMapsApiKey) ?>&libraries=places,geometry"></script>
<?php endif; ?>
<style>
.pac-container{z-index:9999999 !important;font-family:inherit !important;border-radius:10px !important;box-shadow:0 10px 30px rgba(0,0,0,0.2) !important;border:1px solid #cbd5e1 !important;}
.pac-item{padding:9px 12px !important;font-size:14px !important;cursor:pointer;line-height:1.4;}
.pac-item:hover, .pac-item-selected{background-color:#f1f5f9 !important;}
.pac-item-query{font-size:14px !important;color:#0f172a !important;}
.gps-badge.warn{background:#fef3c7 !important;color:#92400e !important;border:1px solid #f59e0b !important;}
.gps-badge.error{background:#fee2e2 !important;color:#991b1b !important;border:1px solid #ef4444 !important;}
.gps-warn-box{display:none;margin-top:.6rem;padding:.65rem .8rem;border-radius:10px;font-size:.78rem;line-height:1.45;text-align:left;}
.gps-warn-box.warn{display:block;background:#fffbeb;border:1px solid #f59e0b;color:#92400e;}
.gps-warn-box.error{display:block;background:#fef2f2;border:1px solid #ef4444;color:#991b1b;}
.gps-warn-box.info{display:block;background:#eff6ff;border:1px solid #3b82f6;color:#1e40af;}
.gps-picker-wrap{display:none;margin-top:.75rem;border:1.5px solid #cbd5e1;border-radius:14px;overflow:hidden;background:#fff;text-align:left;position:relative;box-shadow:0 6px 20px rgba(0,0,0,0.06);}
.gps-picker-wrap.open{display:block;}
.gps-picker-hdr{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.65rem .75rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-wrap:wrap;}
.gps-picker-hdr strong{font-size:.82rem;color:#0f172a;display:flex;align-items:center;gap:.35rem;}
.gps-picker-map{width:100%;height:320px;background:#e2e8f0;position:relative;z-index:1;}
.gps-picker-bar{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.55rem .75rem;background:#f8fafc;border-top:1px solid #e2e8f0;flex-wrap:wrap;}
.gps-picker-coords{font-size:.74rem;font-family:monospace;color:#334155;font-weight:600;}
.gps-picker-btns{display:flex;gap:.4rem;flex-wrap:wrap;}
.gps-picker-btn{border:1px solid #cbd5e1;background:#fff;color:#1e293b;padding:.36rem .7rem;border-radius:8px;font-size:.76rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem;transition:all .15s ease;-webkit-tap-highlight-color:transparent;}
.gps-picker-btn.prim{background:#059669;border-color:#059669;color:#fff;}
.gps-picker-btn:hover{filter:brightness(.96);}
.gps-picker-btn:active{transform:scale(.98);}
.gps-map-hint{position:absolute;top:10px;left:50%;transform:translateX(-50%);z-index:10;background:rgba(15,23,42,0.82);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);color:#fff;font-size:0.72rem;font-weight:600;padding:4px 12px;border-radius:20px;pointer-events:none;box-shadow:0 3px 10px rgba(0,0,0,0.3);display:inline-flex;align-items:center;gap:5px;white-space:nowrap;}
.gps-picker-search-bar{padding:0.45rem 0.65rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;gap:0.4rem;align-items:center;}
.gps-picker-search-input{width:100%;padding:0.4rem 0.65rem 0.4rem 1.85rem;font-size:0.82rem;border:1px solid #cbd5e1;border-radius:8px;outline:none;}
.gps-picker-chips{padding:0.35rem 0.65rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;gap:0.4rem;overflow-x:auto;-webkit-overflow-scrolling:touch;touch-action:pan-x;scrollbar-width:none;align-items:center;}
.gps-picker-chips::-webkit-scrollbar{display:none;}
.gps-chip-btn{border:1px solid #cbd5e1;background:#fff;color:#334155;padding:0.25rem 0.65rem;border-radius:16px;font-size:0.72rem;font-weight:600;cursor:pointer;white-space:nowrap;flex-shrink:0;-webkit-tap-highlight-color:transparent;}
.gps-chip-btn:active{background:#e2e8f0;}

/* Mobile Optimization for GPS Location Picker */
@media (max-width: 640px) {
  .gps-picker-hdr strong{width:100%;justify-content:space-between;margin-bottom:2px;}
  .gps-picker-hdr .gps-picker-btns{width:100%;display:flex;}
  .gps-picker-hdr .gps-picker-btns .gps-picker-btn{flex:1;justify-content:center;min-height:38px;}
  .gps-picker-search-input{font-size:16px !important;min-height:40px;}
  .gps-picker-search-btn{min-height:40px;padding:0.4rem 0.85rem !important;}
  .gps-chip-btn{min-height:32px;padding:0.3rem 0.7rem;}
  .gps-picker-map{height:340px !important;}
  .gps-picker-bar{flex-direction:column !important;align-items:stretch !important;gap:0.5rem !important;padding:0.65rem !important;}
  .gps-picker-coords{text-align:center !important;font-size:0.8rem !important;padding:0.4rem 0.5rem !important;background:#f1f5f9;border-radius:8px;border:1px dashed #cbd5e1;word-break:break-all;}
  .gps-picker-bar .gps-picker-btns{width:100% !important;display:flex !important;gap:0.5rem !important;}
  .gps-picker-bar .gps-picker-btns .gps-picker-btn{flex:1 !important;justify-content:center !important;min-height:44px !important;font-size:0.85rem !important;}
}
.cam-gps-live{padding:.45rem .85rem;background:rgba(15,23,42,.92);color:#e2e8f0;font-size:.78rem;display:flex;align-items:center;justify-content:center;gap:.45rem;border-top:1px solid rgba(255,255,255,.08);}
.cam-gps-live .gps-dot{width:8px;height:8px;border-radius:50%;background:#f59e0b;display:inline-block;}
.cam-gps-live.good .gps-dot{background:#10b981;}
.cam-gps-live.err .gps-dot{background:#ef4444;}
.cam-stage{position:relative;background:#000;overflow:hidden;}
.cam-hud-overlay{position:absolute;left:10px;bottom:10px;right:10px;max-width:340px;background:rgba(15,23,42,.76);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);color:#fff;padding:8px 11px;border-radius:10px;border:1px solid rgba(255,255,255,.16);font-family:'DM Sans',sans-serif;pointer-events:none;line-height:1.35;box-shadow:0 4px 14px rgba(0,0,0,.35);}
.cam-hud-time{color:#67e8f9;font-weight:700;font-size:.82rem;margin-bottom:2px;}
.cam-hud-coord{font-size:.84rem;font-weight:700;font-variant-numeric:tabular-nums;}
.cam-hud-meta{font-size:.72rem;color:#cbd5e1;margin-top:2px;}
.cam-hud-addr{font-size:.72rem;color:#e2e8f0;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
:root{
  --pri:#064e3b;--acc:#10b981;--acc2:#059669;--bg:#f1f5f9;
  --txt:#1e293b;--sub:#64748b;--danger:#ef4444;--warn:#f59e0b;
  --border:#e2e8f0;
  --safe-bottom:env(safe-area-inset-bottom,0px);
  --safe-top:env(safe-area-inset-top,0px);
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
html,body{height:100%;overflow:hidden;width:100%;max-width:100vw;-webkit-text-size-adjust:100%;text-size-adjust:100%;}
body{font-family:'DM Sans',-apple-system,sans-serif;background:var(--bg);color:var(--txt);overflow-x:hidden;}
h1,h2,h3,h4,h5,h6,p,td,th{overflow-wrap:break-word;word-wrap:break-word;}
.badge, .btn, .d-btn, .tb-chip, .drawer-item, .stat-num, .summary-num, .d-hero-date, .lang-switch-btn{white-space:nowrap;word-break:normal;overflow-wrap:normal;}
.app{display:flex;flex-direction:column;height:100vh;height:100dvh;overflow:hidden;min-width:0;width:100%;}
.top-bar{flex-shrink:0;background:#fff;border-bottom:1px solid var(--border);padding:0 1rem;height:56px;display:flex;align-items:center;justify-content:space-between;z-index:100;padding-top:var(--safe-top);}
.menu-btn{background:none;border:none;width:40px;height:40px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;color:var(--pri);cursor:pointer;border-radius:10px;flex-shrink:0;}
.menu-btn:active{background:#f0fdf4;}
.page-title{font-weight:800;font-size:1.05rem;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.top-right{display:flex;align-items:center;gap:.65rem;flex-shrink:0;}
.content-area{flex:1;overflow-y:auto;overflow-x:hidden;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;padding-bottom:calc(1.5rem + var(--safe-bottom));}
.content-inner{padding:1rem;}
.page{display:none;}.page.active{display:block;}
.card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:1.125rem;margin-bottom:1rem;}
.card-title{font-size:1rem;font-weight:700;color:var(--txt);margin-bottom:1rem;}
.hero-card{background:linear-gradient(135deg,#064e3b,#10b981);border-radius:20px;padding:1.375rem 1.25rem;margin-bottom:1rem;color:#fff;position:relative;overflow:hidden;}
.hero-card::before{content:'';position:absolute;top:-30px;right:-30px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.07);}
.hero-greeting{font-size:1.25rem;font-weight:800;margin-bottom:.2rem;}
.hero-sub{font-size:.82rem;opacity:.8;}

/* DASHBOARD STATS   -   per animal type */
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;}
.stat-card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:.875rem;text-align:center;position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:-15px;right:-15px;width:60px;height:60px;border-radius:50%;background:rgba(16,185,129,.06);}
.stat-emoji{font-size:1.5rem;display:block;margin-bottom:.2rem;line-height:1;}
.stat-num{font-size:1.75rem;font-weight:800;line-height:1;background:linear-gradient(135deg,var(--acc),var(--acc2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.stat-num.danger{background:linear-gradient(135deg,var(--danger),#dc2626);-webkit-background-clip:text;background-clip:text;}
.stat-num.warn{background:linear-gradient(135deg,var(--warn),#d97706);-webkit-background-clip:text;background-clip:text;}
.stat-num.info{background:linear-gradient(135deg,#3b82f6,#1d4ed8);-webkit-background-clip:text;background-clip:text;}
.stat-lbl{font-size:.65rem;color:var(--sub);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-top:.2rem;}

/* Summary row */
.summary-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;margin-bottom:1rem;}
.summary-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:.75rem;text-align:center;}
.summary-num{font-size:1.5rem;font-weight:800;line-height:1;}
.summary-num.danger{color:var(--danger);}
.summary-num.warn{color:var(--warn);}
.summary-num.pri{color:var(--acc);}
.summary-lbl{font-size:.62rem;color:var(--sub);font-weight:600;text-transform:uppercase;margin-top:.2rem;}

/* ===== MODERN DASHBOARD ===== */
.dash{max-width:1240px;margin:0 auto;}
.d-hero{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:1.5rem;flex-wrap:wrap;padding:1.75rem;border-radius:22px;color:#fff;margin-bottom:1.25rem;background:radial-gradient(circle at 85% 20%,rgba(52,211,153,.55),transparent 45%),linear-gradient(135deg,#022c22 0%,#064e3b 55%,#059669 100%);box-shadow:0 14px 34px -14px rgba(6,78,59,.55);}
.d-hero::before{content:'';position:absolute;right:-70px;bottom:-90px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.06);}
.d-hero::after{content:'';position:absolute;left:38%;top:-80px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.05);}
.d-hero-main{position:relative;z-index:1;flex:1 1 320px;min-width:0;}
.d-hero-date{display:inline-flex;align-items:center;gap:.4rem;font-size:.72rem;font-weight:600;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);padding:.3rem .7rem;border-radius:9999px;backdrop-filter:blur(6px);}
.d-hero h1{font-size:clamp(1.4rem,2.6vw,2rem);font-weight:800;letter-spacing:-.02em;margin:.8rem 0 .3rem;line-height:1.15;}
.d-hero p{font-size:.9rem;opacity:.82;margin-bottom:1.1rem;}
.d-hero-actions{display:flex;flex-wrap:wrap;gap:.6rem;}
.d-btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border:1px solid transparent;border-radius:12px;padding:.65rem 1.05rem;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;transition:transform .15s,box-shadow .15s,background .15s;min-height:42px;}
.d-btn:hover{transform:translateY(-1px);}
.d-btn-light{background:#fff;color:#064e3b;box-shadow:0 6px 16px -6px rgba(0,0,0,.35);}
.d-btn-ghost{background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.28);}
.d-btn-ghost:hover{background:rgba(255,255,255,.22);}
.d-btn-primary{background:linear-gradient(135deg,#059669,#10b981);color:#fff;box-shadow:0 6px 14px -6px rgba(16,185,129,.7);}
.d-btn-sm{padding:.5rem .85rem;font-size:.76rem;min-height:36px;border-radius:10px;}
.d-hero-side{position:relative;z-index:1;display:flex;align-items:center;gap:1rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:18px;padding:1rem 1.25rem;backdrop-filter:blur(8px);}
.d-hero-ring{--p:0;width:84px;height:84px;border-radius:50%;flex-shrink:0;display:grid;place-items:center;background:conic-gradient(#6ee7b7 calc(var(--p)*1%),rgba(255,255,255,.2) 0);}
.d-hero-ring>div{width:66px;height:66px;border-radius:50%;background:#064e3b;display:flex;flex-direction:column;align-items:center;justify-content:center;line-height:1.05;}
.d-hero-ring strong{font-size:1.15rem;font-weight:800;}
.d-hero-ring span{font-size:.55rem;text-transform:uppercase;letter-spacing:.06em;opacity:.75;font-weight:600;}
.d-hero-side-txt{display:flex;flex-direction:column;}
.d-hero-side-txt strong{font-size:1.7rem;font-weight:800;line-height:1;}
.d-hero-side-txt span{font-size:.74rem;opacity:.8;margin-top:.25rem;max-width:130px;}

.d-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.25rem;}
.d-kpi{display:flex;align-items:center;gap:.9rem;background:#fff;border:1px solid var(--border);border-radius:18px;padding:1.05rem 1.1rem;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:transform .2s,box-shadow .2s;}
.d-kpi:hover{transform:translateY(-3px);box-shadow:0 14px 28px -14px rgba(15,23,42,.22);}
.d-kpi-ico{--c:#10b981;width:48px;height:48px;border-radius:14px;flex-shrink:0;display:grid;place-items:center;font-size:1.3rem;color:var(--c);background:color-mix(in srgb,var(--c) 14%,#fff);}
.d-kpi-body{display:flex;flex-direction:column;min-width:0;}
.d-kpi-body span{font-size:.7rem;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.05em;}
.d-kpi-body strong{font-size:1.65rem;font-weight:800;line-height:1.15;letter-spacing:-.02em;color:var(--txt);}
.d-kpi-body small{font-size:.72rem;color:var(--sub);}

.d-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(0,1fr);gap:1.25rem;align-items:start;}
.d-col{display:flex;flex-direction:column;gap:1.25rem;min-width:0;}
.d-card{background:#fff;border:1px solid var(--border);border-radius:20px;padding:1.25rem;box-shadow:0 1px 2px rgba(15,23,42,.04);}
.d-card-hd{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:1.1rem;}
.d-card-hd h3{font-size:1rem;font-weight:800;letter-spacing:-.01em;color:var(--txt);}
.d-card-hd p{font-size:.74rem;color:var(--sub);margin-top:.15rem;}
.d-link{background:none;border:none;font-family:inherit;font-size:.78rem;font-weight:700;color:var(--acc2);cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .5rem;border-radius:8px;white-space:nowrap;}
.d-link:hover{background:#ecfdf5;}

.d-bars{display:flex;flex-direction:column;gap:1rem;}
.d-bar-row{display:flex;align-items:center;gap:.85rem;}
.d-bar-ico{--c:#10b981;width:42px;height:42px;border-radius:12px;flex-shrink:0;display:grid;place-items:center;font-size:1.3rem;background:color-mix(in srgb,var(--c) 14%,#fff);}
.d-bar-main{flex:1;min-width:0;}
.d-bar-top{display:flex;justify-content:space-between;align-items:baseline;font-size:.82rem;font-weight:600;margin-bottom:.4rem;}
.d-bar-top strong{font-weight:800;}
.d-bar-top em{font-style:normal;font-size:.72rem;color:var(--sub);margin-left:.3rem;}
.d-bar-track{height:8px;border-radius:9999px;background:#f1f5f9;overflow:hidden;}
.d-bar-fill{height:100%;border-radius:9999px;animation:dBar .8s cubic-bezier(.2,.8,.2,1) both;transform-origin:left;}
@keyframes dBar{from{transform:scaleX(0);}to{transform:scaleX(1);}}

.d-farms{display:grid;grid-template-columns:1fr;gap:.75rem;}
.d-farm{display:flex;align-items:center;gap:.9rem;padding:.9rem 1rem;border:1px solid var(--border);border-radius:16px;background:#fff;transition:border-color .15s,box-shadow .15s;}
.d-farm:hover{border-color:#a7f3d0;box-shadow:0 8px 20px -12px rgba(16,185,129,.45);}
.d-farm-av{width:46px;height:46px;border-radius:14px;flex-shrink:0;display:grid;place-items:center;color:#fff;font-weight:800;font-size:1.1rem;background:linear-gradient(135deg,#064e3b,#10b981);}
.d-farm-info{flex:1;min-width:0;}
.d-farm-name{font-weight:800;font-size:.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.d-farm-meta{display:flex;flex-wrap:wrap;gap:.2rem .85rem;margin-top:.3rem;font-size:.73rem;color:var(--sub);}
.d-farm-meta i{color:var(--acc);margin-right:.2rem;}

.d-health{display:flex;align-items:center;gap:1.4rem;flex-wrap:wrap;justify-content:center;}
.d-donut{width:148px;height:148px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;}
.d-donut>div{width:104px;height:104px;border-radius:50%;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:inset 0 0 0 1px var(--border);}
.d-donut strong{font-size:1.5rem;font-weight:800;line-height:1;}
.d-donut span{font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;color:var(--sub);font-weight:700;margin-top:.2rem;}
.d-legend{list-style:none;flex:1;min-width:140px;display:flex;flex-direction:column;gap:.55rem;}
.d-legend li{display:flex;align-items:center;gap:.55rem;font-size:.82rem;font-weight:600;}
.d-legend li i{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.d-legend li span{flex:1;color:var(--sub);}
.d-legend li strong{font-weight:800;}

.d-list{display:flex;flex-direction:column;}
.d-item{display:flex;align-items:center;gap:.8rem;padding:.75rem 0;border-bottom:1px solid #f1f5f9;}
.d-item:last-child{border-bottom:none;padding-bottom:0;}
.d-item:first-child{padding-top:0;}
.d-item-ico{--c:#10b981;width:38px;height:38px;border-radius:11px;flex-shrink:0;display:grid;place-items:center;font-size:1rem;color:var(--c);background:color-mix(in srgb,var(--c) 14%,#fff);}
.d-item-body{flex:1;min-width:0;}
.d-item-t{font-size:.84rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.d-item-s{font-size:.73rem;color:var(--sub);margin-top:.12rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.d-dot{width:9px;height:9px;border-radius:50%;background:var(--danger);flex-shrink:0;}
.d-empty{text-align:center;padding:1.5rem 1rem;color:var(--sub);}
.d-empty i{font-size:2rem;display:block;margin-bottom:.5rem;opacity:.4;}
.d-empty h4{font-size:.92rem;font-weight:700;color:var(--txt);margin-bottom:.25rem;}
.d-empty p{font-size:.78rem;line-height:1.5;}

@media(max-width:1024px){
  .d-grid{grid-template-columns:1fr;}
  .d-kpis{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:560px){
  .d-hero{padding:1.25rem;border-radius:18px;}
  .d-hero-side{width:100%;}
  .d-hero-actions .d-btn{flex:1 1 auto;}
  .d-kpis{gap:.7rem;}
  .d-kpi{padding:.85rem;gap:.7rem;flex-direction:column;align-items:flex-start;}
  .d-kpi-body strong{font-size:1.4rem;}
  .d-card{padding:1rem;border-radius:16px;}
  .d-donut{width:128px;height:128px;}
  .d-donut>div{width:90px;height:90px;}
}

.badge{display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;}
.badge-pending{background:#fef3c7;color:#92400e;}
.badge-approved{background:#d1fae5;color:#065f46;}
.badge-rejected{background:#fee2e2;color:#991b1b;}
.badge-healthy{background:#d1fae5;color:#065f46;}
.badge-sick{background:#fee2e2;color:#991b1b;}
.farm-item{border:1px solid var(--border);border-radius:14px;padding:1rem;display:flex;align-items:center;gap:.875rem;background:#fff;margin-bottom:.75rem;}
.farm-item:active{background:#f0fdf4;}
.farm-avatar{width:46px;height:46px;border-radius:12px;flex-shrink:0;background:linear-gradient(135deg,var(--pri),var(--acc));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.1rem;}
.farm-info{flex:1;min-width:0;}
.farm-name{font-weight:700;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.farm-meta{font-size:.75rem;color:var(--sub);margin-top:.15rem;}
.form-group{margin-bottom:.875rem;}
.form-label{display:block;font-size:.8rem;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.03em;margin-bottom:.4rem;}
.form-input,.form-select{width:100%;padding:.75rem 1rem;border:1.5px solid var(--border);border-radius:12px;font-size:.9rem;font-family:inherit;background:#fff;color:var(--txt);-webkit-appearance:none;appearance:none;transition:.15s;}
.form-input:focus,.form-select:focus{outline:none;border-color:var(--acc);box-shadow:0 0 0 3px rgba(16,185,129,.12);}
textarea.form-input{resize:none;line-height:1.5;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;padding:.75rem 1.25rem;border:none;border-radius:12px;font-weight:700;font-size:.875rem;font-family:inherit;cursor:pointer;transition:.15s;min-height:44px;}
.btn:active{transform:scale(.98);}
.btn-primary{background:var(--acc);color:#fff;}
.btn-primary:active{background:var(--acc2);}
.btn-secondary{background:#f8fafc;color:var(--txt);border:1.5px solid var(--border);}
.btn-danger-sm{background:none;border:none;color:var(--danger);cursor:pointer;font-size:1.1rem;padding:.375rem;min-width:36px;min-height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px;}
.btn-danger-sm:active{background:#fee2e2;}
.btn-edit-sm{background:none;border:none;color:var(--sub);cursor:pointer;font-size:1.1rem;padding:.375rem;min-width:36px;min-height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px;}
.btn-edit-sm:active{background:#f0fdf4;color:var(--acc);}
.btn-submit{background:linear-gradient(135deg,#064e3b,#10b981);color:#fff;border-radius:14px;font-size:.95rem;padding:.875rem 1.5rem;width:100%;border:none;font-weight:700;font-family:inherit;cursor:pointer;min-height:52px;display:flex;align-items:center;justify-content:center;gap:.5rem;}
.btn-submit:active{opacity:.9;}
.section-hdr{background:linear-gradient(135deg,#085041,#10b981);border-radius:16px;padding:1rem 1.125rem;display:flex;align-items:center;gap:.875rem;margin-bottom:1rem;color:#fff;}
.section-seal{width:48px;height:48px;border-radius:50%;flex-shrink:0;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:1.5rem;}
.section-hdr h2{font-size:.875rem;font-weight:700;margin-bottom:.15rem;}
.section-hdr p{font-size:.72rem;opacity:.75;line-height:1.4;}
.form-section{background:#fff;border:1px solid var(--border);border-radius:16px;margin-bottom:.875rem;overflow:hidden;}
.form-section-hdr{background:linear-gradient(90deg,#f0fdf4,#f8fafc);border-bottom:1px solid var(--border);padding:.625rem 1rem;display:flex;align-items:center;gap:.5rem;font-size:.72rem;font-weight:700;color:var(--pri);text-transform:uppercase;letter-spacing:.05em;}
.form-section-num{background:var(--acc);color:#fff;border-radius:50%;width:18px;height:18px;font-size:.65rem;display:flex;align-items:center;justify-content:center;font-weight:800;flex-shrink:0;}
.form-section-body{padding:1rem;}
.toggle-row{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;}
.tog-btn{flex:1;min-width:100px;border:1.5px solid var(--border);border-radius:10px;padding:.5rem .75rem;background:#fff;cursor:pointer;display:flex;align-items:center;gap:.375rem;font-size:.78rem;font-weight:600;color:var(--sub);transition:.15s;font-family:inherit;}
.tog-btn.active{border-color:var(--acc);background:#f0fdf4;color:var(--pri);}
.biz-row{display:flex;gap:.375rem;flex-wrap:wrap;margin-top:.375rem;}
.biz-pill{border:1.5px solid var(--border);border-radius:9999px;padding:.3rem .75rem;font-size:.75rem;font-weight:600;cursor:pointer;background:#fff;color:var(--sub);transition:.15s;font-family:inherit;}
.biz-pill.active{background:var(--acc);color:#fff;border-color:var(--acc);}
.cb-grid{display:grid;grid-template-columns:1fr 1fr;gap:.375rem;margin-top:.375rem;}
.cb-item{display:flex;align-items:center;gap:.5rem;font-size:.8rem;cursor:pointer;padding:.5rem .625rem;border:1.5px solid var(--border);border-radius:8px;transition:.15s;color:var(--sub);user-select:none;}
.cb-item.checked{border-color:var(--acc);background:#d1fae5;color:var(--pri);}
.cb-item input{accent-color:var(--acc);flex-shrink:0;width:14px;height:14px;}
.gps-zone{border:2px dashed var(--border);border-radius:14px;padding:1.25rem 1rem;text-align:center;background:linear-gradient(135deg,#f9fafb,#f3f4f6);margin-bottom:.875rem;}
.gps-zone h4{font-size:.85rem;font-weight:700;color:var(--sub);margin-bottom:.75rem;}
.photo-actions{display:flex;gap:.5rem;}
.btn-cam{flex:1;padding:.625rem .875rem;border-radius:10px;font-size:.8rem;font-weight:600;cursor:pointer;border:none;transition:.15s;display:flex;align-items:center;justify-content:center;gap:.375rem;font-family:inherit;min-height:40px;}
.btn-cam.primary{background:var(--acc);color:#fff;}
.btn-cam.secondary{background:#fff;color:var(--txt);border:1.5px solid var(--border);}
.gps-badge{display:none;align-items:center;justify-content:center;gap:.375rem;background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:8px;padding:.5rem .875rem;font-size:.78rem;font-weight:600;margin-top:.75rem;}
.gps-badge.show{display:flex;}
.photo-preview{display:none;margin-top:.875rem;border-radius:12px;overflow:hidden;border:2px solid var(--acc);position:relative;}
.photo-preview.show{display:block;}
.photo-preview canvas{width:100%;display:block;}
.remove-photo{position:absolute;top:8px;right:8px;background:rgba(239,68,68,.9);color:#fff;border:none;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:.75rem;display:flex;align-items:center;justify-content:center;}

/* DYNAMIC LIVESTOCK ROWS in farm registration */
.lv-dynamic-list{display:flex;flex-direction:column;gap:.625rem;margin-bottom:.875rem;}
.lv-dynamic-row{background:#f8fafc;border:1.5px solid var(--border);border-radius:12px;padding:.875rem;position:relative;animation:fadeIn .2s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:translateY(0);}}
.lv-dynamic-row .remove-lv-row{position:absolute;top:.625rem;right:.625rem;background:none;border:none;color:var(--danger);cursor:pointer;font-size:1rem;width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;}
.lv-dynamic-row .remove-lv-row:active{background:#fee2e2;}
.lv-row-top{display:flex;align-items:center;gap:.5rem;margin-bottom:.625rem;}
.lv-animal-emoji{font-size:1.375rem;flex-shrink:0;}
.lv-row-type-label{font-weight:700;font-size:.9rem;color:var(--pri);}
.lv-row-fields{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;}
.lv-row-fields input{padding:.5rem .75rem;border:1.5px solid var(--border);border-radius:10px;font-size:.83rem;font-family:inherit;background:#fff;width:100%;}
.lv-row-fields input:focus{outline:none;border-color:var(--acc);}
.lv-row-fields .field-full{grid-column:1/-1;}
.btn-add-animal{width:100%;border:2px dashed var(--acc);background:#f0fdf4;color:var(--pri);border-radius:12px;padding:.75rem;font-size:.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.5rem;font-family:inherit;transition:.15s;}
.btn-add-animal:active{background:#d1fae5;}
.animal-picker{display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;margin-top:.875rem;}
.animal-pick-btn{border:1.5px solid var(--border);background:#fff;border-radius:10px;padding:.625rem .375rem;display:flex;flex-direction:column;align-items:center;gap:.25rem;cursor:pointer;font-size:.65rem;font-weight:700;color:var(--sub);transition:.15s;font-family:inherit;}
.animal-pick-btn:active{border-color:var(--acc);background:#f0fdf4;color:var(--pri);}
.animal-pick-btn span{font-size:1.4rem;line-height:1;}
.animal-picker-wrap{background:#fff;border:1px solid var(--border);border-radius:14px;padding:.875rem;margin-bottom:.875rem;display:none;}
.animal-picker-wrap.show{display:block;}

/* ===== MODERN LIVESTOCK WEB APP STYLES ===== */
.lv-app-header {
  background: linear-gradient(135deg, #064e3b 0%, #047857 55%, #10b981 100%);
  border-radius: 20px;
  padding: 1.5rem 1.4rem;
  margin-bottom: 1.25rem;
  color: #fff;
  position: relative;
  overflow: hidden;
  box-shadow: 0 10px 25px -5px rgba(6, 78, 59, 0.25);
}
.lv-app-header::after {
  content: '';
  position: absolute;
  top: -45px;
  right: -45px;
  width: 170px;
  height: 170px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.08);
  pointer-events: none;
}
.lv-badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255, 255, 255, 0.16);
  backdrop-filter: blur(8px);
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.25);
}
.lv-pulse-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #34d399;
  box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7);
  animation: lvPulse 2s infinite;
}
@keyframes lvPulse {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 7px rgba(52, 211, 153, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0); }
}
.lv-header-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
@media (min-width: 768px) {
  .lv-header-content {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}
.lv-header-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.lv-btn-primary {
  background: #fff;
  color: #064e3b;
  border: none;
  font-weight: 700;
  font-size: 0.84rem;
  padding: 0.65rem 1.15rem;
  border-radius: 12px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  font-family: inherit;
}
.lv-btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
  background: #f0fdf4;
}
.lv-btn-glass {
  background: rgba(255, 255, 255, 0.16);
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.28);
  font-weight: 600;
  font-size: 0.84rem;
  padding: 0.65rem 1rem;
  border-radius: 12px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s;
  font-family: inherit;
  backdrop-filter: blur(8px);
}
.lv-btn-glass:hover {
  background: rgba(255, 255, 255, 0.26);
}

/* 2. Top Metric KPI Dashboard Cards */
.lv-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}
@media (min-width: 900px) {
  .lv-kpi-grid {
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
  }
}
.lv-kpi-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 1rem;
  position: relative;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.lv-kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
}
.lv-kpi-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  margin-bottom: 0.65rem;
}
.lv-kpi-val {
  font-size: 1.6rem;
  font-weight: 800;
  line-height: 1.1;
  color: var(--txt);
  margin-bottom: 0.25rem;
  display: flex;
  align-items: baseline;
  gap: 6px;
}
.lv-kpi-lbl {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--sub);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.lv-kpi-sub {
  font-size: 0.72rem;
  color: var(--sub);
  margin-top: 0.35rem;
  display: flex;
  align-items: center;
  gap: 4px;
}

/* Livestock Visual Charts Panel */
.lv-charts-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
  margin-bottom: 1.25rem;
}
@media (min-width: 860px) {
  .lv-charts-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
.lv-chart-card {
  background: #ffffff;
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 1.15rem 1.25rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.lv-chart-card:hover {
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
}
.lv-chart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.85rem;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.lv-chart-title {
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--txt);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
}
.lv-chart-title i {
  color: var(--acc);
  font-size: 1.05rem;
}
.lv-chart-badge {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.2rem 0.6rem;
  border-radius: 9999px;
  background: #f1f5f9;
  color: var(--sub);
}
.lv-chart-body {
  position: relative;
  flex: 1;
  min-height: 220px;
  max-height: 260px;
}
.lv-dropdown-wrap {
  position: relative;
  display: inline-block;
}
.lv-dropdown-menu {
  display: none;
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  background: #ffffff;
  border: 1px solid var(--border);
  border-radius: 14px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
  min-width: 270px;
  z-index: 100;
  padding: 6px;
  animation: lvFadeIn 0.15s ease-out;
}
.lv-dropdown-menu.show {
  display: block;
}
@keyframes lvFadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.lv-menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 10px;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--txt);
  text-decoration: none;
  cursor: pointer;
  transition: background 0.15s ease;
  border: none;
  background: transparent;
  width: 100%;
  text-align: left;
}
.lv-menu-item:hover {
  background: #f1f5f9;
  color: var(--acc2);
}
.lv-menu-item i {
  font-size: 1.15rem;
  flex-shrink: 0;
}

/* 3. Species Filter Ribbon */
.lv-ribbon-wrap {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  overflow-x: auto;
  padding-bottom: 0.5rem;
  margin-bottom: 1rem;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.lv-ribbon-wrap::-webkit-scrollbar {
  display: none;
}
.lv-chip-btn {
  background: #fff;
  border: 1.5px solid var(--border);
  padding: 0.45rem 0.95rem;
  border-radius: 9999px;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--sub);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
  transition: all 0.18s ease;
  font-family: inherit;
}
.lv-chip-btn:hover {
  border-color: #cbd5e1;
  color: var(--txt);
}
.lv-chip-btn.active {
  background: #ecfdf5;
  border-color: #10b981;
  color: #064e3b;
  box-shadow: 0 2px 6px rgba(16, 185, 129, 0.15);
}
.lv-chip-count {
  background: #f1f5f9;
  color: var(--sub);
  font-size: 0.7rem;
  padding: 1px 6px;
  border-radius: 9999px;
  font-weight: 800;
}
.lv-chip-btn.active .lv-chip-count {
  background: #10b981;
  color: #fff;
}

/* 4. Toolbar: Search, Filters & View Switcher */
.lv-toolbar-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 0.875rem 1rem;
  margin-bottom: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}
@media (min-width: 820px) {
  .lv-toolbar-card {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}
.lv-search-box {
  position: relative;
  flex: 1;
  max-width: 360px;
}
.lv-search-box i.bi-search {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--sub);
  font-size: 0.85rem;
}
.lv-search-input {
  width: 100%;
  padding: 0.55rem 2rem 0.55rem 2.25rem;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  font-size: 0.83rem;
  background: #f8fafc;
  font-family: inherit;
  transition: all 0.2s;
}
.lv-search-input:focus {
  outline: none;
  background: #fff;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
}
.lv-search-clear {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--sub);
  cursor: pointer;
  display: none;
  font-size: 0.95rem;
}
.lv-filters-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.lv-select {
  padding: 0.5rem 1.8rem 0.5rem 0.75rem;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E") no-repeat calc(100% - 8px) center;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--txt);
  cursor: pointer;
  font-family: inherit;
  appearance: none;
}
.lv-view-toggles {
  display: flex;
  background: #f1f5f9;
  padding: 3px;
  border-radius: 10px;
  border: 1px solid var(--border);
}
.lv-view-btn {
  background: none;
  border: none;
  padding: 5px 10px;
  border-radius: 7px;
  color: var(--sub);
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 5px;
  transition: all 0.15s;
  font-family: inherit;
}
.lv-view-btn.active {
  background: #fff;
  color: var(--txt);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

/* 5. Grid View Cards */
.lv-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
  margin-bottom: 1.5rem;
}
@media (min-width: 680px) {
  .lv-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (min-width: 1120px) {
  .lv-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
.lv-card {
  background: #fff;
  border: 1.5px solid var(--border);
  border-radius: 18px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}
.lv-card:hover {
  transform: translateY(-3px);
  border-color: #cbd5e1;
  box-shadow: 0 10px 24px -5px rgba(0, 0, 0, 0.08);
}
.lv-card-top {
  padding: 1.15rem 1.15rem 0.85rem;
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
}
.lv-avatar {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
}
.lv-card-main {
  flex: 1;
  min-width: 0;
}
.lv-card-title {
  font-weight: 800;
  font-size: 0.95rem;
  color: var(--txt);
  display: flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.lv-farm-badge {
  font-size: 0.72rem;
  color: var(--sub);
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.lv-tag-strip {
  background: #f8fafc;
  border-top: 1px dashed var(--border);
  border-bottom: 1px dashed var(--border);
  padding: 0.5rem 1.15rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.lv-tag-chip {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.78rem;
  font-weight: 700;
  color: #064e3b;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  background: #ecfdf5;
  padding: 3px 8px;
  border-radius: 6px;
  border: 1px solid rgba(16, 185, 129, 0.3);
  transition: all 0.15s;
}
.lv-tag-chip:hover {
  background: #d1fae5;
}
.lv-qty-pill {
  font-size: 0.75rem;
  font-weight: 800;
  color: #047857;
  background: #e6fffa;
  padding: 3px 8px;
  border-radius: 6px;
}
.lv-vitals-grid {
  padding: 0.85rem 1.15rem;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}
.lv-vital-cell {
  background: #f8fafc;
  padding: 0.45rem 0.65rem;
  border-radius: 8px;
  font-size: 0.73rem;
}
.lv-vital-lbl {
  color: var(--sub);
  font-size: 0.65rem;
  text-transform: uppercase;
  font-weight: 700;
  display: block;
  margin-bottom: 1px;
}
.lv-vital-val {
  font-weight: 700;
  color: var(--txt);
}
.lv-notes-callout {
  margin: 0 1.15rem 0.85rem;
  background: #f8fafc;
  border-left: 3px solid #10b981;
  border-radius: 0 8px 8px 0;
  padding: 0.45rem 0.65rem;
  font-size: 0.73rem;
  color: var(--sub);
  line-height: 1.4;
  font-style: italic;
}
.lv-card-footer {
  margin-top: auto;
  border-top: 1px solid #f1f5f9;
  padding: 0.65rem 1.15rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fafafa;
}
.lv-card-btns {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.lv-action-btn {
  border: 1px solid var(--border);
  background: #fff;
  color: var(--txt);
  padding: 5px 9px;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: all 0.15s;
  font-family: inherit;
}
.lv-action-btn:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}
.lv-action-btn.danger {
  color: #ef4444;
  border-color: #fee2e2;
  background: #fef2f2;
}
.lv-action-btn.danger:hover {
  background: #fee2e2;
}
.lv-action-btn.warn {
  color: #d97706;
  border-color: #fef3c7;
  background: #fffbeb;
}
.lv-action-btn.warn:hover {
  background: #fef3c7;
}

/* 6. Modern High-Density Table View */
.lv-table-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
  margin-bottom: 1.5rem;
}
.lv-table-wrap {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.lv-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.82rem;
}
.lv-table th {
  background: #f8fafc;
  color: var(--sub);
  font-weight: 700;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0.85rem 1rem;
  border-bottom: 1.5px solid var(--border);
  white-space: nowrap;
}
.lv-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.lv-table tr:last-child td {
  border-bottom: none;
}
.lv-table tr:hover td {
  background: #fafafa;
}

/* 7. Status & Health Indicator Badges */
.lv-status-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 4px;
}
.lv-status-healthy { background: #10b981; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2); }
.lv-status-sick { background: #ef4444; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2); }
.lv-status-recovered { background: #06b6d4; box-shadow: 0 0 0 2px rgba(6, 182, 212, 0.2); }
.lv-status-deceased { background: #64748b; }

.lv-pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 9999px;
  font-size: 0.72rem;
  font-weight: 700;
  white-space: nowrap;
}
.lv-pill-healthy { background: #ecfdf5; color: #047857; border: 1px solid rgba(16, 185, 129, 0.25); }
.lv-pill-sick { background: #fef2f2; color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.25); }
.lv-pill-recovered { background: #ecfeff; color: #0891b2; border: 1px solid rgba(6, 182, 212, 0.25); }
.lv-pill-deceased { background: #f1f5f9; color: #475569; border: 1px solid rgba(100, 116, 139, 0.25); }

.lv-vax-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 7px;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 700;
}
.lv-vax-complete { background: #ecfdf5; color: #059669; }
.lv-vax-partial { background: #fef3c7; color: #b45309; }
.lv-vax-none { background: #f1f5f9; color: #64748b; }

/* 8. Farm Grouped View */
.lv-farm-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 1.15rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}
.lv-farm-header {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding-bottom: 0.875rem;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--border);
}
.lv-farm-avatar {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, var(--pri), var(--acc));
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-weight: 800;
  font-size: 1.15rem;
  flex-shrink: 0;
  box-shadow: 0 4px 10px rgba(6, 78, 59, 0.15);
}
.lv-farm-title {
  font-weight: 800;
  font-size: 1rem;
  color: var(--txt);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.lv-farm-subtitle {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  margin-top: 2px;
}
.lv-farm-count-badge {
  text-align: right;
  flex-shrink: 0;
}
.lv-farm-count-num {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--acc2);
}
.lv-farm-count-lbl {
  font-size: 0.65rem;
  color: var(--sub);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* Legacy Support */
.livestock-card{border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:.875rem;}
.livestock-row{display:flex;align-items:center;gap:.875rem;padding:.875rem 1rem;border-bottom:1px solid #f1f5f9;background:#fff;}
.livestock-row:last-child{border-bottom:none;}
.lv-icon{width:38px;height:38px;border-radius:10px;background:#f0fdf4;color:var(--acc);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
.lv-info{flex:1;min-width:0;}
.lv-tag{font-weight:700;font-size:.85rem;color:var(--pri);}
.lv-meta{font-size:.73rem;color:var(--sub);margin-top:.1rem;}
.lv-qty{font-size:1.1rem;font-weight:800;color:var(--acc);text-align:right;white-space:nowrap;margin-right:.25rem;}
.lv-actions{display:flex;gap:.15rem;}
.incident-hdr{background:linear-gradient(135deg,#fef3c7,#fed7aa);border:1px solid #fecaca;border-radius:16px;padding:1.125rem;margin-bottom:1rem;text-align:center;}
.incident-hdr h2{font-size:1.1rem;font-weight:800;color:#92400e;margin-bottom:.25rem;}
.incident-hdr p{font-size:.78rem;color:#b45309;line-height:1.5;}
.severity-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin:.875rem 0;}
.severity-card{border:2px solid var(--border);border-radius:12px;padding:.75rem .5rem;text-align:center;cursor:pointer;transition:.15s;background:#fff;}
.severity-card.active{border-color:var(--acc);background:#ecfdf5;}
.severity-card:active{transform:scale(.97);}
.severity-icon{font-size:1.75rem;display:block;margin-bottom:.15rem;}
.severity-card div{font-weight:700;font-size:.82rem;}
.severity-card small{font-size:.68rem;color:var(--sub);}
.alert-banner{background:linear-gradient(135deg,#fef3c7,#fed7aa);border:1px solid #fecaca;border-radius:12px;padding:.875rem;margin-bottom:1rem;display:flex;gap:.75rem;align-items:flex-start;}
.capture-zone{border:2.5px dashed var(--border);border-radius:16px;padding:1.5rem 1rem;text-align:center;background:linear-gradient(135deg,#f9fafb,#f3f4f6);margin:.875rem 0;cursor:pointer;transition:.2s;}
.capture-zone:active{border-color:var(--acc);background:#f0fdf4;}
.disease-tags{display:flex;flex-wrap:wrap;gap:.375rem;margin:.75rem 0;}
.disease-tag{padding:.3rem .625rem;border-radius:20px;font-size:.75rem;font-weight:600;background:#f3f4f6;color:#6b7280;cursor:pointer;transition:.15s;}
.disease-tag.active{background:var(--acc);color:#fff;}
.notif-item {
  display: flex;
  gap: .875rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: 14px;
  margin-bottom: .75rem;
  background: #fff;
  transition: all .2s ease;
  position: relative;
  cursor: pointer;
}
.notif-item:hover {
  border-color: #cbd5e1;
  box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}
.notif-item.unread {
  background: #f8fafc;
  border-left: 4px solid var(--acc);
}
.notif-item.unread.is-alert {
  background: #fff5f5;
  border-left: 4px solid var(--danger);
}
.notif-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.15rem;
}
.notif-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--acc);
  display: inline-block;
  margin-left: 6px;
  box-shadow: 0 0 6px rgba(16,185,129,0.6);
  flex-shrink: 0;
}
.notif-dot.alert {
  background: var(--danger);
  box-shadow: 0 0 6px rgba(239,68,68,0.6);
}
.notif-tab {
  padding: .4rem .85rem;
  border-radius: 20px;
  font-size: .8rem;
  font-weight: 600;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid transparent;
  cursor: pointer;
  white-space: nowrap;
  transition: all .15s ease;
}
.notif-tab:hover {
  background: #e2e8f0;
}
.notif-tab.active {
  background: var(--acc);
  color: #fff;
  border-color: var(--acc);
}
.notif-action-btn {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #475569;
  font-size: .75rem;
  font-weight: 600;
  padding: .25rem .6rem;
  border-radius: 6px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: .15s;
}
.notif-action-btn:hover {
  background: var(--acc);
  border-color: var(--acc);
  color: #fff;
}
.notif-action-btn.delete-btn {
  background: #fef2f2;
  border-color: #fee2e2;
  color: #dc2626;
}
.notif-action-btn.delete-btn:hover {
  background: #dc2626;
  border-color: #dc2626;
  color: #ffffff;
}
.tb-bell.has-unread {
  border-color: #fed7aa;
}
.tb-bell.has-unread i {
  animation: bellRing 3s ease infinite;
  color: #ea580c;
}
@keyframes bellRing {
  0%, 80%, 100% { transform: rotate(0); }
  85% { transform: rotate(14deg); }
  90% { transform: rotate(-14deg); }
  95% { transform: rotate(7deg); }
}
.notif-toast-container {
  position: fixed;
  top: 70px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 10px;
  pointer-events: none;
  max-width: 380px;
  width: calc(100vw - 40px);
}
.notif-toast {
  pointer-events: auto;
  background: #ffffff;
  border-radius: 12px;
  padding: 12px 16px;
  box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15), 0 8px 10px -6px rgba(0,0,0,0.1);
  border: 1px solid var(--border);
  border-left: 4px solid var(--acc);
  display: flex;
  gap: 12px;
  align-items: flex-start;
  animation: slideInToast .3s ease forwards;
  transition: opacity .3s, transform .3s;
  cursor: pointer;
}
.notif-toast.is-alert {
  border-left-color: var(--danger);
  background: #fffafa;
}
@keyframes slideInToast {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

/* ===== MODERN AGRI CHAT SYSTEM ===== */
#pg-chat.active {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 56px);
    height: calc(100dvh - 56px);
    margin: -1rem;
    overflow: hidden;
    background: #ffffff;
}
.chat-shell {
    display: flex;
    flex-direction: row;
    height: 100%;
    width: 100%;
    background: #ffffff;
    overflow: hidden;
    position: relative;
}
.chat-sidebar {
    width: 360px;
    min-width: 310px;
    max-width: 380px;
    flex-shrink: 0;
    height: 100%;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    background: #ffffff;
    z-index: 10;
}
.chat-sidebar-hdr {
    padding: .9rem 1.1rem;
    background: #ffffff;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
}
.chat-hdr-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--txt);
    display: flex;
    align-items: center;
    gap: .5rem;
}
.chat-count-pill {
    font-size: .7rem;
    font-weight: 700;
    padding: .2rem .55rem;
    border-radius: 9999px;
    background: #ecfdf5;
    color: var(--acc2);
    border: 1px solid rgba(16, 185, 129, 0.25);
}
.chat-search-wrap {
    padding: .75rem 1rem .5rem;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
}
.chat-search-box {
    position: relative;
    display: flex;
    align-items: center;
}
.chat-search-box i {
    position: absolute;
    left: .85rem;
    color: var(--sub);
    font-size: .85rem;
    pointer-events: none;
}
.chat-search-input {
    width: 100%;
    padding: .55rem .85rem .55rem 2.2rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    font-size: .85rem;
    background: #f8fafc;
    outline: none;
    font-family: inherit;
    transition: all .15s;
}
.chat-search-input:focus {
    background: #ffffff;
    border-color: var(--acc);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
}
.chat-tabs {
    display: flex;
    gap: .35rem;
    padding: .4rem 1rem .65rem;
    background: #ffffff;
    border-bottom: 1px solid var(--border);
    overflow-x: auto;
}
.chat-tab-btn {
    padding: .35rem .75rem;
    border-radius: 9999px;
    font-size: .75rem;
    font-weight: 700;
    font-family: inherit;
    border: 1px solid var(--border);
    background: #f8fafc;
    color: var(--sub);
    cursor: pointer;
    white-space: nowrap;
    transition: all .15s;
}
.chat-tab-btn.active {
    background: var(--pri);
    color: #ffffff;
    border-color: var(--pri);
}
.chat-farmer-list {
    flex: 1;
    overflow-y: auto;
    background: #ffffff;
}
.farmer-item {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .85rem 1rem;
    border-bottom: 1px solid #f8fafc;
    cursor: pointer;
    transition: background .15s;
    position: relative;
}
.farmer-item:hover {
    background: #f8fafc;
}
.farmer-item.active {
    background: #ecfdf5;
    border-left: 3px solid var(--acc2);
}
.farmer-av-wrap {
    position: relative;
    flex-shrink: 0;
}
.farmer-avatar-sm {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: .95rem;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.farmer-avatar-sm.role-official {
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
}
.farmer-avatar-sm.role-farmer {
    background: linear-gradient(135deg, #064e3b, #10b981);
}
.online-dot {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: #10b981;
    border: 2px solid #ffffff;
}
.farmer-item-info {
    flex: 1;
    min-width: 0;
}
.farmer-item-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .4rem;
    margin-bottom: .2rem;
}
.farmer-name-txt {
    font-weight: 700;
    font-size: .88rem;
    color: var(--txt);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.farmer-time-txt {
    font-size: .68rem;
    color: var(--sub);
    white-space: nowrap;
    font-weight: 500;
}
.farmer-item-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
}
.farmer-preview {
    font-size: .75rem;
    color: var(--sub);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.role-tag-badge {
    display: inline-flex;
    align-items: center;
    font-size: .62rem;
    font-weight: 700;
    padding: .1rem .45rem;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: .02em;
    flex-shrink: 0;
}
.role-tag-badge.official {
    background: #dbeafe;
    color: #1e40af;
}
.role-tag-badge.farmer {
    background: #d1fae5;
    color: #065f46;
}
.unread-badge {
    background: var(--danger);
    color: #ffffff;
    font-size: .65rem;
    font-weight: 800;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.chat-conv-view {
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    background: #f8fafc;
    position: relative;
}
.chat-no-conv {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;
    background: #f8fafc;
}
.chat-no-conv-icon {
    width: 76px;
    height: 76px;
    border-radius: 24px;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    color: #64748b;
    margin-bottom: 1.25rem;
}
.chat-no-conv h3 {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--txt);
    margin-bottom: .4rem;
}
.chat-no-conv p {
    font-size: .84rem;
    color: var(--sub);
    max-width: 360px;
    line-height: 1.5;
}
.chat-active-pane {
    display: none;
    flex: 1;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}
.chat-active-pane.show {
    display: flex;
}
.chat-hdr-mini {
    padding: .75rem 1.25rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    background: #ffffff;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.chat-back {
    display: none;
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--pri);
    cursor: pointer;
    padding: .35rem;
    border-radius: 10px;
    min-width: 36px;
    min-height: 36px;
    align-items: center;
    justify-content: center;
}
.chat-hdr-left {
    display: flex;
    align-items: center;
    gap: .75rem;
    min-width: 0;
    flex: 1;
}
.chat-hdr-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--pri), var(--acc));
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: .95rem;
    flex-shrink: 0;
}
.chat-hdr-avatar.role-official {
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
}
.chat-hdr-name {
    font-weight: 800;
    font-size: .96rem;
    color: var(--txt);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: .4rem;
    min-width: 0;
}
.chat-hdr-status {
    font-size: .72rem;
    color: var(--sub);
    display: flex;
    align-items: center;
    gap: .3rem;
}
.chat-hdr-status .status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #10b981;
}
.chat-hdr-actions {
    display: flex;
    gap: .5rem;
    flex-shrink: 0;
}
.call-btn-sm {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    border: 1px solid transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .95rem;
    transition: transform .15s, background .15s;
}
.call-btn-sm:hover {
    transform: translateY(-1px);
}
.voice-btn {
    background: #ecfdf5;
    color: #065f46;
    border-color: #a7f3d0;
}
.video-btn {
    background: #eff6ff;
    color: #1e40af;
    border-color: #bfdbfe;
}
.chat-messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem;
    background-color: #f8fafc;
    background-image: radial-gradient(#cbd5e1 0.75px, transparent 0.75px);
    background-size: 20px 20px;
    display: flex;
    flex-direction: column;
    gap: .75rem;
}
.chat-welcome-banner {
    margin: 1.5rem auto;
    max-width: 420px;
    text-align: center;
    padding: 1.25rem;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(8px);
    border: 1px solid var(--border);
    border-radius: 18px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
}
.chat-welcome-banner i {
    font-size: 2rem;
    color: var(--acc);
    display: block;
    margin-bottom: .5rem;
}
.chat-welcome-banner h4 {
    font-size: .92rem;
    font-weight: 800;
    color: var(--txt);
    margin-bottom: .3rem;
}
.chat-welcome-banner p {
    font-size: .78rem;
    color: var(--sub);
    line-height: 1.45;
}
.msg-row {
    display: flex;
    max-width: 78%;
    align-items: flex-end;
    gap: .5rem;
}
.msg-row.them {
    align-self: flex-start;
}
.msg-row.mine {
    align-self: flex-end;
    flex-direction: row-reverse;
}
.msg-bubble {
    padding: .65rem .95rem;
    border-radius: 16px;
    font-size: .88rem;
    line-height: 1.5;
    word-break: break-word;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.msg-bubble.them {
    background: #ffffff;
    color: var(--txt);
    border-bottom-left-radius: 4px;
    border: 1px solid #e2e8f0;
}
.msg-bubble.mine {
    background: linear-gradient(135deg, #064e3b, #10b981);
    color: #ffffff;
    border-bottom-right-radius: 4px;
}
.msg-meta {
    font-size: .65rem;
    margin-top: .25rem;
    display: flex;
    align-items: center;
    gap: .25rem;
    padding: 0 .2rem;
}
.msg-row.them .msg-meta {
    color: var(--sub);
    justify-content: flex-start;
}
.msg-row.mine .msg-meta {
    color: var(--sub);
    justify-content: flex-end;
}
.msg-img-wrap {
    overflow: hidden;
    border-radius: 14px;
    max-width: 280px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.msg-img-wrap img {
    display: block;
    width: 100%;
    height: auto;
    cursor: pointer;
    transition: transform .2s;
}
.msg-img-wrap img:hover {
    transform: scale(1.02);
}
.chat-quick-tags {
    display: flex;
    gap: .4rem;
    padding: .4rem 1rem;
    background: #ffffff;
    border-top: 1px solid #f1f5f9;
    overflow-x: auto;
}
.quick-chip {
    padding: .25rem .65rem;
    border-radius: 9999px;
    font-size: .72rem;
    font-weight: 600;
    background: #f8fafc;
    border: 1px solid var(--border);
    color: var(--sub);
    cursor: pointer;
    white-space: nowrap;
    transition: all .15s;
}
.quick-chip:hover {
    background: #ecfdf5;
    color: var(--acc2);
    border-color: #a7f3d0;
}
.chat-media-preview-bar {
    display: none;
    align-items: center;
    gap: .75rem;
    padding: .5rem 1.25rem;
    background: #f8fafc;
    border-top: 1px solid var(--border);
}
.chat-media-preview-bar.show {
    display: flex;
}
.chat-preview-thumb {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid var(--border);
}
.chat-preview-info {
    flex: 1;
    font-size: .75rem;
    color: var(--sub);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.chat-preview-remove {
    background: none;
    border: none;
    color: var(--danger);
    font-size: 1.1rem;
    cursor: pointer;
    padding: .25rem;
}
.chat-input-bar {
    padding: .75rem 1.25rem;
    border-top: 1px solid var(--border);
    background: #ffffff;
    display: flex;
    gap: .65rem;
    align-items: flex-end;
    flex-shrink: 0;
}
.chat-attach-btn {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #f1f5f9;
    border: 1px solid var(--border);
    color: var(--sub);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.15rem;
    transition: all .15s;
    flex-shrink: 0;
}
.chat-attach-btn:hover {
    background: #ecfdf5;
    color: var(--acc);
    border-color: #a7f3d0;
}
.chat-input {
    flex: 1;
    padding: .65rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: 14px;
    font-size: .88rem;
    font-family: inherit;
    outline: none;
    background: #f8fafc;
    resize: none;
    max-height: 100px;
    line-height: 1.4;
    transition: border-color .15s, background .15s;
}
.chat-input:focus {
    border-color: var(--acc);
    background: #ffffff;
}
.chat-send-btn {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #064e3b, #10b981);
    color: #ffffff;
    border: none;
    border-radius: 12px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.28);
    transition: transform .15s, box-shadow .15s;
}
.chat-send-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

@media (max-width: 768px) {
    .chat-sidebar {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        border-right: none;
    }
    .chat-conv-view {
        position: absolute;
        inset: 0;
        z-index: 60;
        transform: translateX(100%);
        transition: transform .25s ease;
    }
    .chat-conv-view.show {
        transform: translateX(0);
    }
    .chat-back {
        display: flex;
    }
    .chat-no-conv {
        display: none !important;
    }
}

/* WEBRTC CALL OVERLAY */
.call-overlay{position:fixed;inset:0;background:rgba(6,78,59,.97);z-index:8000;display:none;flex-direction:column;align-items:center;justify-content:space-between;padding:3rem 2rem calc(3rem + var(--safe-bottom));color:#fff;}
.call-overlay.show{display:flex;}
.call-overlay-avatar{width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.2);border:4px solid rgba(255,255,255,.4);display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:800;margin-bottom:1rem;}
.call-overlay-name{font-size:1.5rem;font-weight:800;margin-bottom:.5rem;}
.call-overlay-status{font-size:.9rem;opacity:.7;margin-bottom:2rem;}
.call-local-video{position:absolute;top:1rem;right:1rem;width:100px;height:140px;border-radius:12px;object-fit:cover;border:2px solid rgba(255,255,255,.3);background:#000;display:none;}
.call-remote-video{width:100%;max-height:40vh;border-radius:16px;object-fit:cover;background:#000;display:none;}
.call-btn-row{display:flex;gap:1.5rem;align-items:center;}
.call-action-btn{width:60px;height:60px;border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.4rem;transition:.15s;}
.call-action-btn:active{transform:scale(.93);}
.call-action-btn.mute{background:rgba(255,255,255,.2);color:#fff;}
.call-action-btn.mute.active{background:#fff;color:#064e3b;}
.call-action-btn.cam-toggle{background:rgba(255,255,255,.2);color:#fff;}
.call-action-btn.cam-toggle.active{background:#fff;color:#064e3b;}
.call-action-btn.hangup{background:#ef4444;color:#fff;width:70px;height:70px;font-size:1.6rem;}
.call-action-btn.answer{background:#10b981;color:#fff;width:70px;height:70px;font-size:1.6rem;}
.incoming-call-banner{position:fixed;top:0;left:0;right:0;background:linear-gradient(135deg,#064e3b,#10b981);color:#fff;z-index:7900;padding:1rem 1.25rem;display:none;align-items:center;gap:1rem;box-shadow:0 4px 20px rgba(0,0,0,.3);}
.incoming-call-banner.show{display:flex;}
.incoming-info{flex:1;}
.incoming-title{font-weight:800;font-size:.95rem;}
.incoming-sub{font-size:.75rem;opacity:.8;}
.incoming-btns{display:flex;gap:.5rem;}
.perm-error-banner{background:#fee2e2;border:1px solid #fecaca;border-radius:12px;padding:.875rem;margin:1rem;display:none;gap:.75rem;align-items:flex-start;font-size:.82rem;color:#991b1b;}
.perm-error-banner.show{display:flex;}

.cert-action-bar{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center;}
.cert-status-badge{display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .875rem;border-radius:9999px;font-size:.72rem;font-weight:800;flex-shrink:0;}
.cert-valid{background:#d1fae5;color:#065f46;border:2px solid #10b981;}
.cert-expired{background:#fee2e2;color:#991b1b;border:2px solid #ef4444;}
/* ===== MODERN SAAS PROFILE CARD STYLES ===== */
#pg-profile {
  display: none;
}
#pg-profile.active {
  min-height: 80vh;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  background: radial-gradient(circle at 50% 12%, rgba(16, 185, 129, 0.06) 0%, transparent 65%);
  padding: 1.5rem 1rem 3rem;
}
.profile-card-wrapper {
  max-width: 560px;
  width: 100%;
  margin: 1rem auto 2.5rem;
  background: #ffffff;
  border-radius: 24px;
  box-shadow: 0 16px 40px -12px rgba(0, 0, 0, 0.09), 0 0 0 1px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.profile-card-wrapper:hover {
  box-shadow: 0 20px 48px -12px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0, 0, 0, 0.06);
}
.profile-card-header {
  background: linear-gradient(145deg, #064e3b 0%, #047857 45%, #059669 100%);
  padding: 34px 24px 26px;
  text-align: center;
  position: relative;
  color: #ffffff;
  overflow: hidden;
}
.profile-card-header::before {
  content: '';
  position: absolute;
  top: -50px;
  right: -50px;
  width: 180px;
  height: 180px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, transparent 70%);
  pointer-events: none;
}
.profile-card-header::after {
  content: '';
  position: absolute;
  bottom: -40px;
  left: -30px;
  width: 140px;
  height: 140px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
  pointer-events: none;
}
.profile-avatar-circle {
  width: 78px;
  height: 78px;
  margin: 0 auto 12px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.18);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 3px solid rgba(255, 255, 255, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.9rem;
  font-weight: 800;
  color: #ffffff;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
  overflow: hidden;
  position: relative;
  z-index: 1;
}
.profile-avatar-circle img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}
.profile-header-name {
  font-family: 'Syne', sans-serif;
  font-size: 1.35rem;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 3px;
  line-height: 1.25;
  letter-spacing: -0.01em;
  position: relative;
  z-index: 1;
}
.profile-header-role {
  font-size: 0.86rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.85);
  margin: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  position: relative;
  z-index: 1;
}
.profile-verified-chip {
  background: #10b981;
  color: #fff;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  box-shadow: 0 2px 6px rgba(16, 185, 129, 0.4);
  margin-left: 6px;
  letter-spacing: 0.02em;
  vertical-align: middle;
}
.profile-copy-btn {
  opacity: 0;
  background: none;
  border: none;
  color: #059669;
  cursor: pointer;
  font-size: 0.8rem;
  padding: 2px 5px;
  border-radius: 4px;
  transition: opacity 0.15s ease, background 0.15s ease, transform 0.15s;
  display: inline-flex;
  align-items: center;
  margin-left: 4px;
  vertical-align: middle;
}
.profile-info-row:hover .profile-copy-btn {
  opacity: 1;
}
.profile-copy-btn:hover {
  background: #ecfdf5;
  transform: scale(1.1);
}
.profile-card-body {
  padding: 24px 28px 28px;
}
.profile-sec-heading {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.92rem;
  font-weight: 700;
  color: #047857;
  margin-top: 1.5rem;
  margin-bottom: 0.65rem;
}
.profile-sec-heading:first-of-type {
  margin-top: 0;
}
.profile-sec-heading i {
  font-size: 1.05rem;
  color: #059669;
}
.profile-info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 11px 4px;
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.15s ease, padding 0.15s ease;
}
.profile-info-row:hover {
  background: #f8fafc;
  padding-left: 8px;
  padding-right: 8px;
  border-radius: 8px;
}
.profile-info-label {
  font-size: 0.86rem;
  font-weight: 500;
  color: #64748b;
}
.profile-info-value {
  font-size: 0.88rem;
  font-weight: 600;
  color: #0f172a;
  text-align: right;
  max-width: 60%;
  word-break: break-word;
}
.profile-card-actions {
  display: flex;
  justify-content: center;
  gap: 12px;
  margin-top: 26px;
  padding-top: 6px;
}
.profile-btn-edit {
  background: #059669;
  color: #ffffff;
  font-weight: 700;
  font-size: 0.9rem;
  padding: 10px 28px;
  border-radius: 9999px;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
  transition: all 0.2s ease;
  font-family: inherit;
}
.profile-btn-edit:hover {
  background: #047857;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(5, 150, 105, 0.45);
}
.profile-btn-pw {
  background: #f8fafc;
  color: #334155;
  font-weight: 700;
  font-size: 0.9rem;
  padding: 10px 22px;
  border-radius: 9999px;
  border: 1px solid #e2e8f0;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  transition: all 0.2s ease;
  font-family: inherit;
}
.profile-btn-pw:hover {
  background: #e2e8f0;
  color: #0f172a;
  transform: translateY(-2px);
}
.pf-preview-avatar {
  width: 72px;
  height: 72px;
  border-radius: 18px;
  background: #f1f5f9;
  border: 2px dashed #cbd5e1;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  font-size: 1.5rem;
  color: var(--sub);
  flex-shrink: 0;
}
.pf-preview-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Modal Desktop Centering Enhancement */
@media (min-width: 640px) {
  .modal-wrap {
    align-items: center;
    padding: 1.5rem;
  }
  .modal-box {
    border-radius: 24px;
    max-width: 580px;
    width: 100%;
    animation: modalPop 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  }
}
@keyframes modalPop {
  from { transform: scale(0.96) translateY(8px); opacity: 0; }
  to { transform: scale(1) translateY(0); opacity: 1; }
}
.drawer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1500;opacity:0;visibility:hidden;transition:.25s;}
.drawer-overlay.show{opacity:1;visibility:visible;}
.drawer{position:fixed;left:-100%;top:0;width:80%;max-width:300px;height:100vh;background:var(--pri);z-index:2000;transition:.3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;padding-top:var(--safe-top);}
.drawer.open{left:0;}
.drawer-hdr{display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.1);}
.drawer-brand-wrap{display:flex;flex-direction:column;}
.brand{font-size:1.5rem;font-weight:800;color:#fff;}.brand span{color:var(--acc);}
.portal-lbl{font-size:.65rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.08em;margin-top:.2rem;display:block;}
.drawer, .drawer-nav, #drawer { scrollbar-width: none !important; -ms-overflow-style: none !important; }
.drawer::-webkit-scrollbar, .drawer-nav::-webkit-scrollbar, #drawer::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; background: transparent !important; -webkit-appearance: none !important; }
.drawer::-webkit-scrollbar-thumb, .drawer-nav::-webkit-scrollbar-thumb, #drawer::-webkit-scrollbar-thumb { display: none !important; width: 0 !important; height: 0 !important; background: transparent !important; }
.drawer::-webkit-scrollbar-track, .drawer-nav::-webkit-scrollbar-track, #drawer::-webkit-scrollbar-track { display: none !important; background: transparent !important; }
.drawer::-webkit-scrollbar-button, .drawer-nav::-webkit-scrollbar-button, #drawer::-webkit-scrollbar-button { display: none !important; width: 0 !important; height: 0 !important; }
.drawer-nav{flex:1;padding:.75rem 0;overflow-y:auto;overflow-x:hidden;scrollbar-width: none !important; -ms-overflow-style: none !important;}
.drawer-item{display:flex;align-items:center;gap:.625rem;padding:.75rem 1.125rem;margin:.15rem .625rem;color:rgba(255,255,255,.75);text-decoration:none;border-radius:.75rem;font-weight:500;font-size:.875rem;cursor:pointer;transition:.15s;}
.drawer-item i{font-size:1.05rem;width:1.125rem;text-align:center;flex-shrink:0;}
.drawer-item:active,.drawer-item.active{background:var(--acc);color:#fff;}

/* DROPDOWN SIDEBAR STYLES */
.drawer-dropdown-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .75rem 1.125rem;
  margin: .15rem .625rem;
  color: rgba(255,255,255,.8);
  border-radius: .75rem;
  font-weight: 600;
  font-size: .875rem;
  cursor: pointer;
  user-select: none;
  transition: all .2s ease;
}
.drawer-dropdown-btn:hover {
  background: rgba(255,255,255,.08);
  color: #fff;
}
.drawer-dropdown-btn.open {
  background: rgba(255,255,255,.12);
  color: #fff;
}
.drop-chevron {
  font-size: .8rem;
  transition: transform .25s ease;
}
.drawer-dropdown-btn.open .drop-chevron {
  transform: rotate(180deg);
}

.drawer-submenu {
  max-height: 0;
  opacity: 0;
  overflow: hidden;
  transition: max-height .3s cubic-bezier(0.4, 0, 0.2, 1), opacity .25s ease;
  padding-left: .5rem;
}
.drawer-submenu.open {
  max-height: 300px;
  opacity: 1;
}
.drawer-subitem {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .6rem 1rem .6rem 1.75rem;
  margin: .15rem .625rem .15rem 0;
  color: rgba(255,255,255,.7);
  border-radius: .6rem;
  font-size: .82rem;
  font-weight: 500;
  cursor: pointer;
  transition: all .15s ease;
  position: relative;
}
.drawer-subitem::before {
  content: '';
  position: absolute;
  left: .85rem;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: rgba(255,255,255,.3);
  transition: background .15s ease;
}
.drawer-subitem:hover {
  color: #fff;
  background: rgba(255,255,255,.06);
}
.drawer-subitem.active {
  background: var(--acc, #10b981);
  color: #fff;
  font-weight: 700;
}
.drawer-subitem.active::before {
  background: #fff;
}

.sub-status-badge {
  font-size: .62rem;
  font-weight: 700;
  padding: .15rem .45rem;
  border-radius: 9999px;
  text-transform: uppercase;
  letter-spacing: .3px;
  margin-left: auto;
}
.sub-status-badge.badge-pending {
  background: #fef3c7;
  color: #b45309;
}
.sub-status-badge.badge-accepted {
  background: #d1fae5;
  color: #065f46;
}
.sub-status-badge.badge-declined {
  background: #fee2e2;
  color: #991b1b;
}

/* Page status notification banner */
.form-status-banner {
  padding: .85rem 1.25rem;
  border-radius: 12px;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  box-shadow: 0 2px 8px rgba(0,0,0,.05);
}
.form-status-banner.banner-pending {
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
}
.form-status-banner.banner-accepted {
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  color: #065f46;
}
.form-status-banner.banner-declined {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

/* FOLIO PRINT & RESPONSIVE SHEET STYLES */
@page { size: 8.5in 13in; margin: 0.4in; }
@media print {
  body * { visibility: hidden !important; }
  .print-area, .print-area * { visibility: visible !important; }
  .print-area {
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    min-height: 0 !important;
    max-height: 12.2in !important;
    page-break-after: avoid !important;
    page-break-inside: avoid !important;
    break-after: avoid !important;
    break-inside: avoid !important;
    padding: 0 !important;
    margin: 0 !important;
    border: none !important;
    box-shadow: none !important;
    overflow: hidden !important;
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
  }
  .no-print { display: none !important; }
}

.folio-sheet-viewport {
  width: 100%;
  display: flex;
  justify-content: center;
  padding: 1rem 0;
  overflow-x: auto;
}
.folio-sheet-wrap {
  width: 8.5in;
  min-height: 13in;
  max-width: 8.5in;
  background: #ffffff;
  color: #111111;
  padding: 0.35in 0.4in;
  box-shadow: 0 4px 20px rgba(0,0,0,0.12);
  border-radius: 4px;
  position: relative;
  box-sizing: border-box;
  font-family: Arial, Calibri, 'Segoe UI', sans-serif;
  font-size: 8.5pt;
  line-height: 1.25;
}

/* PCIC SPECIFIC */
.pcic-form-container { width: 100%; }
.pcic-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 4px; border-bottom: 2px solid #1E7B3A; }
.pcic-brand { display: flex; gap: 8px; align-items: center; }
.pcic-logo { width: 44px; height: 44px; border: 1.5px solid #1E7B3A; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 8px; font-weight: 800; color: #1E7B3A; line-height: 1; flex-shrink: 0; }
.pcic-republic { font-size: 7.5pt; text-transform: uppercase; color: #333; }
.pcic-agency { font-size: 11pt; font-weight: 800; color: #1E7B3A; font-family: Georgia, serif; line-height: 1.1; }
.pcic-region { font-size: 8pt; margin-top: 1px; color: #444; }
.pcic-code { text-align: right; font-size: 8pt; font-weight: 700; }
.pcic-code .code-badge { background: #1E7B3A; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 8pt; }
.pcic-title-row { text-align: center; margin: 4px 0 3px; }
.pcic-form-title { font-size: 10pt; font-weight: 800; text-decoration: underline; color: #111; }
.pcic-instructions { font-size: 7pt; font-style: italic; color: #555; }

.pcic-summary-tbl { width: 100%; border-collapse: collapse; margin-bottom: 3px; font-size: 7.5pt; }
.pcic-summary-tbl td { border: 1px solid #111; padding: 2px 5px; vertical-align: top; }

.pcic-sec-hdr { background: #E6E6E6; font-size: 8pt; font-weight: 800; text-transform: uppercase; padding: 2px 5px; border: 1px solid #111; margin-top: 4px; }
.pcic-subhead { font-size: 7.5pt; font-weight: 700; margin: 3px 0 1px; color: #111; }
.pcic-lbl { display: block; font-size: 6.5pt; font-weight: 700; text-transform: uppercase; color: #555; margin-bottom: 1px; }

.pcic-grid-row { display: flex; gap: 6px; align-items: flex-end; }
.pcic-grid-row input[type=text], .pcic-grid-row input[type=date], .pcic-grid-row select {
  width: 100%; border: none; border-bottom: 1px solid #111; border-radius: 0; background: transparent; padding: 1px 2px; font-size: 8pt; font-weight: 600; outline: none; font-family: inherit;
}
.pcic-grid-row input:focus, .pcic-grid-row select:focus { background: #fffbe6; }

.pcic-compact-types { width: 100%; border-collapse: collapse; font-size: 7.5pt; margin-top: 2px; }
.pcic-compact-types td { border: 1px solid #111; padding: 2px 4px; vertical-align: top; width: 16.66%; line-height: 1.15; }
.pcic-compact-types small { font-size: 6.5pt; color: #555; display: block; margin-top: 1px; }

.pcic-animal-tbl { width: 100%; border-collapse: collapse; margin-top: 2px; font-size: 7pt; }
.pcic-animal-tbl th, .pcic-animal-tbl td { border: 1px solid #111; padding: 1.5px 3px; vertical-align: middle; }
.pcic-animal-tbl th { background: #f0f0f0; font-weight: 700; text-align: center; }
.pcic-animal-tbl input { width: 100%; border: none; background: transparent; font-size: 7pt; padding: 1px; outline: none; }
.pcic-tbl-note { font-size: 6.5pt; font-style: italic; color: #555; margin-top: 2px; }

.pcic-b-footer-tbl { width: 100%; border-collapse: collapse; margin-top: 3px; font-size: 7.5pt; }
.pcic-b-footer-tbl td { border: 1px solid #111; padding: 2px 4px; vertical-align: top; }
.pcic-b-footer-tbl input { border: none; border-bottom: 1px solid #111; background: transparent; font-size: 7.5pt; width: 100%; }

.pcic-c-grid { display: flex; gap: 8px; border: 1px solid #111; border-top: none; padding: 4px; align-items: center; }
.pcic-c-text { flex: 2.2; font-size: 7pt; line-height: 1.25; }
.pcic-c-sig { flex: 1; border-left: 1px dashed #aaa; padding-left: 8px; text-align: center; }
.sig-line { border-bottom: 1px solid #111; padding-top: 25px; text-align: center; }
.sig-name { font-size: 8pt; font-weight: 700; text-transform: uppercase; display: block; }
.sig-title { font-size: 6.5pt; color: #555; display: block; }

.pcic-office-hdr { background: #dbeafe; border-color: #93c5fd; color: #1e3a8a; }
.pcic-office-tbl { width: 100%; border-collapse: collapse; font-size: 7.5pt; background: #f8fafc; }
.pcic-office-tbl td { border: 1px solid #cbd5e1; padding: 2px 5px; vertical-align: top; }
.pcic-office-tbl input { border: none; border-bottom: 1px solid #94a3b8; background: transparent; font-size: 7.5pt; }

.pcic-form-foot { display: flex; justify-content: space-between; font-size: 6.5pt; color: #64748b; margin-top: 4px; border-top: 1px solid #e2e8f0; padding-top: 2px; }

/* RSBSA SPECIFIC */
.rsbsa-form-container { width: 100%; }
.rsbsa-hdr-grid { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #008751; padding-bottom: 3px; }
.rsbsa-hdr-left { display: flex; gap: 8px; align-items: center; }
.rsbsa-logo-box { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.rsbsa-republic { font-size: 7.5pt; font-weight: 700; text-transform: uppercase; color: #222; }
.rsbsa-title { font-size: 10.5pt; font-weight: 900; color: #008751; line-height: 1.1; }
.rsbsa-subtitle { font-size: 7pt; font-weight: 700; color: #444; }

.rsbsa-photo-box { width: 62px; height: 62px; border: 1px dashed #111; background: #FDECD2; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 7pt; font-weight: 800; color: #555; text-align: center; line-height: 1.1; }

.rsbsa-top-bar { display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border: 1px solid #000; border-top: none; padding: 2px 6px; font-size: 7.5pt; }
.rsbsa-ref-in { border: 1px solid #000; background: #FDECD2; padding: 1px 4px; outline: none; }

.rsbsa-banner { background: #000000; color: #ffffff; font-weight: 900; font-size: 7.5pt; padding: 2px 5px; text-transform: uppercase; letter-spacing: 0.5px; }
.rsbsa-grid-row { display: flex; gap: 3px; }
.rsbsa-cell { border: 1px solid #000; padding: 2px 4px; box-sizing: border-box; }
.rsbsa-bg-beige { background: #FDECD2; }
.rsbsa-lbl { display: block; font-size: 6.5pt; font-weight: 800; text-transform: uppercase; color: #111; line-height: 1; }
.rsbsa-in { width: 100%; border: none; background: transparent; font-size: 7.5pt; font-weight: 700; padding: 1px 0; outline: none; font-family: inherit; }
.rsbsa-in:focus { background: #fff; }

.rsbsa-subhead { font-size: 7pt; font-weight: 800; text-transform: uppercase; margin: 2px 0 1px; color: #000; }

.rsbsa-livelihood-box { display: flex; border: 1px solid #000; background: #FDECD2; }
.rsbsa-live-col { flex: 1; border-right: 1px solid #000; padding: 2px 4px; font-size: 7pt; }
.rsbsa-live-col:last-child { border-right: none; }
.rsbsa-sub-chk { display: flex; flex-direction: column; gap: 1px; margin-top: 2px; font-size: 6.8pt; }

.rsbsa-parcel-tbl { width: 100%; border-collapse: collapse; font-size: 7pt; }
.rsbsa-parcel-tbl th, .rsbsa-parcel-tbl td { border: 1px solid #000; padding: 1.5px 3px; }
.rsbsa-parcel-tbl th { background: #000; color: #fff; font-weight: 800; font-size: 6.8pt; text-align: left; }
.rsbsa-tbl-in { width: 100%; border: none; background: transparent; font-size: 7pt; padding: 1px 0; outline: none; }

.rsbsa-dec-text { font-size: 6.8pt; font-style: italic; line-height: 1.2; padding: 2px 4px; border: 1px solid #000; border-top: none; background: #fff; }

.rsbsa-bot-grid { display: flex; border: 1px solid #000; border-top: none; }
.rsbsa-sig-box { flex: 2; padding: 3px 6px; border-right: 1px solid #000; display: flex; flex-direction: column; justify-content: flex-end; }
.rsbsa-sig-line { border-bottom: 1px solid #000; padding-top: 22px; text-align: center; }
.rsbsa-sig-name { font-size: 8pt; font-weight: 800; text-transform: uppercase; display: block; }
.rsbsa-sig-lbl { font-size: 6pt; font-weight: 800; color: #333; display: block; }

.rsbsa-thumb-box { width: 75px; padding: 3px; border-right: 1px solid #000; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.thumb-square { width: 44px; height: 50px; border: 1px dashed #000; display: flex; align-items: center; justify-content: center; font-size: 6.5pt; font-weight: 700; color: #666; background: #fff; }

.rsbsa-verif-box { flex: 3; display: flex; flex-direction: column; padding: 3px 6px; justify-content: space-around; }
.verif-col { width: 100%; }
.verif-line { border-bottom: 1px solid #000; padding-top: 14px; text-align: center; }
.verif-title { font-size: 6.5pt; font-weight: 800; text-transform: uppercase; display: block; }
.verif-sub { font-size: 5.5pt; color: #555; display: block; }

.rsbsa-form-foot { display: flex; justify-content: space-between; font-size: 6.5pt; color: #333; margin-top: 3px; padding-top: 2px; }

.drawer-footer{padding:1.125rem;border-top:1px solid rgba(255,255,255,.1);}
.drawer-user{display:flex;align-items:center;gap:.625rem;}
.drawer-avatar{overflow:hidden;width:36px;height:36px;background:var(--acc);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;}
.modal-wrap{position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);z-index:5000;display:none;align-items:flex-end;justify-content:center;}
.modal-wrap.open{display:flex;}
.modal-box{background:#fff;border-radius:24px 24px 0 0;width:100%;max-height:90vh;overflow-y:auto;padding-bottom:var(--safe-bottom);animation:slideUp .25s ease;}
@keyframes slideUp{from{transform:translateY(100%);}to{transform:translateY(0);}}
.modal-hdr{padding:1.125rem 1.25rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);position:sticky;top:0;background:#fff;z-index:1;}
.modal-hdr h3{font-size:1rem;font-weight:700;color:var(--pri);}
.modal-close{background:#f1f5f9;border:none;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--sub);cursor:pointer;}
.modal-body{padding:1.125rem 1.25rem;}
.modal-footer{padding:.875rem 1.25rem;border-top:1px solid var(--border);display:flex;gap:.5rem;background:#f8fafc;border-radius:0 0 24px 24px;}
.modal-footer .btn{flex:1;}
.cam-modal{position:fixed;inset:0;background:rgba(15,23,42,.8);z-index:9000;display:none;align-items:flex-end;justify-content:center;}
.cam-modal.open{display:flex;}
.cam-box{background:#111;border-radius:24px 24px 0 0;width:100%;overflow:hidden;padding-bottom:var(--safe-bottom);}
.cam-hdr-bar{background:#1a1a2e;padding:.875rem 1.125rem;display:flex;justify-content:space-between;align-items:center;color:#fff;}
.cam-video{width:100%;max-height:55vh;display:block;object-fit:cover;}
.cam-ctrl-bar{padding:.875rem;display:flex;gap:1rem;justify-content:center;align-items:center;background:#111;}
.cam-btn{width:56px;height:56px;border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.3rem;}
.cam-btn.capture{background:var(--acc);color:#fff;width:64px;height:64px;font-size:1.5rem;}
.cam-btn.flip{background:rgba(255,255,255,.15);color:#fff;}
.cam-btn.close-cam{background:rgba(255,255,255,.1);color:#ccc;}
.toast{position:fixed;top:.875rem;left:50%;transform:translateX(-50%) translateY(-120px);z-index:9999;padding:.875rem 1.125rem;border-radius:14px;color:#fff;font-weight:600;font-size:.875rem;box-shadow:0 8px 24px rgba(0,0,0,.2);transition:transform .3s ease;pointer-events:none;text-align:center;white-space:nowrap;max-width:90vw;}
.toast.show{transform:translateX(-50%) translateY(0);}
.toast-success{background:var(--acc);}
.toast-error{background:var(--danger);}
.toast-warn{background:var(--warn);}
.empty-state{text-align:center;padding:3rem 1.5rem;color:var(--sub);}
.empty-state i{font-size:3rem;display:block;margin-bottom:1rem;opacity:.35;}
.empty-state h4{font-size:1rem;font-weight:700;margin-bottom:.5rem;color:var(--txt);}
.empty-state p{font-size:.85rem;line-height:1.5;}

/* ===== MODERN SHELL (desktop sidebar + glass top bar) ===== */
body{background:radial-gradient(1200px 500px at 90% -10%,rgba(16,185,129,.10),transparent 60%),var(--bg);}
.top-bar{background:rgba(255,255,255,.88);backdrop-filter:saturate(180%) blur(14px);-webkit-backdrop-filter:saturate(180%) blur(14px);}
.page-title{font-size:1.05rem;font-weight:800;letter-spacing:-.01em;}
.top-right{display:flex;align-items:center;gap:.65rem;flex-shrink:0;}
.lang-switch-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;background:#ffffff;border:1.5px solid var(--border);color:var(--txt);cursor:pointer;font-size:0.8rem;font-weight:700;font-family:inherit;transition:all 0.15s ease;box-shadow:0 1px 2px rgba(0,0,0,0.04);white-space:nowrap;line-height:1;}
.lang-switch-btn:hover{background:#f0fdf4;border-color:var(--acc);color:var(--pri);}
.lang-switch-btn:active{transform:scale(.97);}
.tb-chip{display:flex;align-items:center;gap:.55rem;padding:.25rem .75rem .25rem .25rem;border:1px solid var(--border);border-radius:999px;background:#fff;white-space:nowrap;}
.tb-chip .tb-av{overflow:hidden;width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:.82rem;color:#fff;background:linear-gradient(135deg,#10b981,#065f46);flex-shrink:0;}
.tb-chip span{font-size:.82rem;font-weight:700;color:var(--txt);max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tb-bell{position:relative;width:38px;height:38px;border-radius:50%;border:1px solid var(--border);background:#fff;display:grid;place-items:center;color:var(--pri);cursor:pointer;font-size:1.05rem;flex-shrink:0;transition:.15s;}
.tb-bell:hover{background:#f0fdf4;border-color:var(--acc);}
.tb-bell b{position:absolute;top:-3px;right:-3px;min-width:17px;height:17px;border-radius:999px;background:var(--danger);color:#fff;font-size:.62rem;display:grid;place-items:center;padding:0 4px;}
.drawer{background:linear-gradient(180deg,#022c22 0%,#064e3b 100%);box-shadow:20px 0 50px -20px rgba(0,0,0,.4);}
.drawer-hdr{padding:1.6rem 1.35rem 1.25rem;}
.drawer-item{display:flex;align-items:center;gap:.75rem;padding:.78rem 1.15rem;margin:.22rem .75rem;color:rgba(255,255,255,.8);text-decoration:none;border-radius:12px;font-weight:600;font-size:.875rem;cursor:pointer;transition:all .15s ease;white-space:nowrap;}
.drawer-item i{font-size:1.1rem;width:1.25rem;text-align:center;flex-shrink:0;}
.drawer-item:hover{background:rgba(255,255,255,.09);color:#fff;}
.drawer-item.active{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 4px 14px -3px rgba(16,185,129,.6);}
.drawer-footer{background:rgba(0,0,0,.18);}
.d-hero{border-radius:26px;box-shadow:0 22px 44px -18px rgba(5,150,105,.55);}
.d-kpi{border-radius:20px;}
.d-card{border-radius:22px;box-shadow:0 8px 24px -14px rgba(15,23,42,.18);}
/* -------------------------------- AUTO-HIDE & FULL VIEW MODE (Farmer Panel) ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */
body.sidebar-autohide .drawer {
  position: fixed !important;
  left: 0 !important;
  top: 0 !important;
  bottom: 0 !important;
  width: 270px !important;
  min-width: 270px !important;
  max-width: 270px !important;
  height: 100vh !important;
  transform: translateX(-100%) !important;
  z-index: 1000 !important;
  box-shadow: 20px 0 50px rgba(0,0,0,0.45) !important;
  transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

body.sidebar-autohide .drawer.hover-open,
body.sidebar-autohide .drawer:hover,
body.sidebar-autohide .drawer.open {
  transform: translateX(0) !important;
}

body.sidebar-autohide .app {
  width: 100vw !important;
  max-width: 100vw !important;
  flex: 1 1 100% !important;
  margin-left: 0 !important;
}

.menu-btn {
  display: flex !important;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

/* Left Edge Hover Detection Zone */
.farmer-hover-zone {
  display: none;
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  width: 20px;
  z-index: 998;
}
body.sidebar-autohide .farmer-hover-zone {
  display: block;
}

/* Floating Edge Trigger Tab */
.farmer-edge-trigger {
  display: none;
  position: fixed;
  top: 50%;
  left: 0;
  transform: translateY(-50%);
  width: 22px;
  height: 54px;
  background: linear-gradient(180deg, #065f46, #022c22);
  color: #34d399;
  border-radius: 0 10px 10px 0;
  box-shadow: 2px 0 12px rgba(0, 0, 0, 0.3);
  z-index: 999;
  cursor: pointer;
  place-items: center;
  font-size: 0.85rem;
  border: 1px solid rgba(52, 211, 153, 0.3);
  border-left: none;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  user-select: none;
}
.farmer-edge-trigger:hover {
  width: 28px;
  background: #059669;
  color: #ffffff;
}
body.sidebar-autohide .farmer-edge-trigger {
  display: grid;
}

.btn-drawer-pin {
  margin-left: auto;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: #a7f3d0;
  display: grid;
  place-items: center;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-drawer-pin:hover {
  background: rgba(255, 255, 255, 0.2);
  color: #fff;
}
body:not(.sidebar-autohide) .btn-drawer-pin {
  color: #34d399;
  background: rgba(52, 211, 153, 0.18);
  border-color: rgba(52, 211, 153, 0.35);
}

.btn-drawer-close {
  display: none;
  background: rgba(255, 255, 255, 0.12);
  border: none;
  color: #fff;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  cursor: pointer;
  margin-left: auto;
  transition: all 0.2s ease;
  touch-action: manipulation;
}
.btn-drawer-close:hover {
  background: rgba(255, 255, 255, 0.22);
}
.btn-drawer-close:active {
  background: rgba(255, 255, 255, 0.3);
  transform: scale(0.95);
}

@media (min-width:1024px){
  body{display:flex;overflow:hidden;}
  .btn-drawer-close{display:none !important;}
  .drawer{position:static;left:0;width:264px;min-width:264px;max-width:264px;height:100vh;flex-shrink:0;transition:transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);}
  .drawer-overlay{display:none !important;}
  .app{margin-left:0;flex:1;min-width:0;width:auto;height:100vh;overflow:hidden;transition:all 0.28s ease;}
  .top-bar{height:64px;padding:0 1.75rem;}
  .content-inner{padding:1.5rem 1.75rem;}
  .modal-wrap{align-items:center;}
  .modal-box{border-radius:24px;max-width:560px;}
  .modal-footer{border-radius:0 0 24px 24px;}
}
@media (max-width:1023px){ 
  .tb-chip span{display:none;} 
  .tb-chip{padding:.25rem;} 
  .content-inner{padding: 1.15rem 1rem;}

  /* Mobile Drawer & Overlay Hierarchy: Drawer MUST be above overlay */
  .drawer-overlay {
    z-index: 1500 !important;
  }
  .drawer,
  body.sidebar-autohide .drawer {
    z-index: 2000 !important;
    width: 280px !important;
    max-width: 85vw !important;
  }
  .drawer.open,
  .drawer.hover-open,
  body.sidebar-autohide .drawer.open,
  body.sidebar-autohide .drawer.hover-open {
    left: 0 !important;
    transform: translateX(0) !important;
  }

  .btn-drawer-close {
    display: flex !important;
    background: rgba(255, 255, 255, 0.12);
    border: none;
    color: #fff;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    margin-left: auto;
    transition: all 0.2s ease;
    touch-action: manipulation;
  }
  .btn-drawer-close:active {
    background: rgba(255, 255, 255, 0.25);
    transform: scale(0.95);
  }

  /* Completely disable desktop edge triggers & hover detection on touch/mobile */
  .farmer-hover-zone,
  .farmer-edge-trigger,
  .btn-drawer-pin {
    display: none !important;
    pointer-events: none !important;
  }

  /* Ensure top-bar and menu-btn are fully clickable above any background elements */
  .top-bar {
    z-index: 1200 !important;
    position: relative;
    padding: 0 12px;
    height: 58px;
    gap: 8px;
  }
  .menu-btn {
    position: relative;
    z-index: 1201 !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    min-width: 44px;
    min-height: 44px;
    cursor: pointer !important;
    pointer-events: auto !important;
    touch-action: manipulation;
  }

  .drawer-item,
  .drawer-dropdown-btn,
  .drawer-subitem {
    cursor: pointer !important;
    pointer-events: auto !important;
    touch-action: manipulation;
    min-height: 44px;
  }

  /* Mobile Menu Buttons: Solid, Opaque, High-Contrast */
  .drawer-item {
    background: #064e3b !important;
    border: 1px solid rgba(255, 255, 255, 0.18) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
    margin-bottom: 7px !important;
    border-radius: 12px !important;
  }
  .drawer-item i {
    color: #34d399 !important;
  }
  .drawer-item:active,
  .drawer-item:hover {
    background: #046c4e !important;
    color: #ffffff !important;
  }
  .drawer-item.active {
    background: linear-gradient(135deg, #10b981, #059669) !important;
    border-color: #34d399 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.45) !important;
  }
  .drawer-item.active i {
    color: #ffffff !important;
  }
  .drawer-dropdown-btn {
    background: #064e3b !important;
    border: 1px solid rgba(255, 255, 255, 0.18) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
    margin-bottom: 7px !important;
    border-radius: 12px !important;
  }
  .drawer-dropdown-btn i {
    color: #34d399 !important;
  }
  .drawer-dropdown-btn.open {
    background: #055a42 !important;
    border-color: rgba(52, 211, 153, 0.5) !important;
    color: #ffffff !important;
  }
  .drawer-submenu {
    background: rgba(0, 0, 0, 0.45) !important;
    border-left: 3px solid #10b981 !important;
    border-radius: 10px !important;
    padding: 6px 8px 6px 10px !important;
    margin: 4px 0 10px 10px !important;
  }
  .drawer-subitem {
    background: #044333 !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    color: #ffffff !important;
    border-radius: 10px !important;
    margin-bottom: 5px !important;
  }
  .drawer-subitem:active,
  .drawer-subitem:hover {
    background: #065f46 !important;
    color: #ffffff !important;
  }
  .drawer-subitem.active {
    background: linear-gradient(135deg, #10b981, #059669) !important;
    border-color: #34d399 !important;
    color: #ffffff !important;
  }

  /* Clean App-Like Experience: Remove Visible Scrollbars on Mobile */
  * {
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
  }
  *::-webkit-scrollbar,
  html::-webkit-scrollbar,
  body::-webkit-scrollbar,
  .content-area::-webkit-scrollbar,
  ::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
    background: transparent !important;
    -webkit-appearance: none !important;
  }
  *::-webkit-scrollbar-thumb,
  *::-webkit-scrollbar-track,
  *::-webkit-scrollbar-button,
  *::-webkit-scrollbar-corner,
  ::-webkit-scrollbar-thumb,
  ::-webkit-scrollbar-track {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
    background: transparent !important;
  }
}
@media (max-width:768px){
  .top-bar{padding:0 .75rem;height:56px;}
  .page-title{flex:1;min-width:0;max-width:none;font-size:clamp(0.92rem, 3.5vw, 1.05rem);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .btn-top-req-assist span { display: none !important; }
  .btn-top-req-assist { padding: .42rem .65rem !important; }
  .content-inner{padding: 1rem .75rem;}
  .d-hero{padding: 1.35rem 1.1rem;}
  .d-hero-greet{font-size: clamp(1.2rem, 4.5vw, 1.6rem);}
  .d-kpis{gap: .6rem;}
  .d-kpi{padding: .75rem; border-radius: 14px;}
  .form-input, .form-select, .form-group input, .form-group select, textarea, .modal-body input{font-size:16px !important;}
  .modal-box{border-radius:20px 20px 0 0;max-height:92vh;}

  /* Universal mobile table touch-scroll */
  .table-responsive,
  .table-scroll,
  .lv-table-wrap,
  .an-table-wrap {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    display: block !important;
    margin-bottom: .75rem !important;
  }
  table.table,
  table.lv-table,
  table.an-table,
  table.dn-table {
    min-width: 520px !important;
  }
}
@media (max-width:480px){
  .content-inner{padding:.75rem .5rem;}
  .top-bar{padding:0 .5rem;}
  .page-title{font-size:clamp(0.88rem, 3.8vw, 1rem);}
  .lang-switch-btn .lang-label{display:none;}
  .lang-switch-btn{padding:5px 8px;}
  .tb-chip span{display:none !important;}
  .tb-chip{padding:0 !important;border:none !important;background:transparent !important;}
  .d-kpis{grid-template-columns:1fr 1fr;gap:.5rem;}
  .stats-grid{grid-template-columns:1fr 1fr;gap:.5rem;}
  .stat-card{padding:.65rem;}
  .stat-num{font-size:1.4rem;}
  div[style*="grid-template-columns:1fr 1fr"],
  div[style*="grid-template-columns: 1fr 1fr"]{grid-template-columns:1fr !important;}
  .animal-picker{grid-template-columns:repeat(3,1fr);gap:.4rem;}
  .lv-row-fields{grid-template-columns:1fr;}
  
  .btn-row{flex-direction:column;}
  .btn-row .btn{width:100%;}

  /* Folio form toolbar responsive stack */
  .page-top-actions {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: .5rem !important;
    padding: .75rem !important;
  }
  .page-top-actions > div {
    width: 100% !important;
    flex-wrap: wrap !important;
    gap: .4rem !important;
  }
  .page-top-actions .btn {
    flex: 1 1 auto !important;
    font-size: .76rem !important;
    padding: .45rem .6rem !important;
    justify-content: center !important;
  }

  /* Compact Services Hub on small mobile */
  .farmer-services-hub {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: .55rem !important;
  }
  .farmer-services-hub > div {
    padding: .75rem .6rem !important;
    gap: .45rem !important;
  }
  .farmer-services-hub > div > div:first-child {
    width: 34px !important;
    height: 34px !important;
    font-size: 1rem !important;
    flex-shrink: 0 !important;
  }
  .farmer-services-hub strong {
    font-size: .78rem !important;
    line-height: 1.15 !important;
  }
  .farmer-services-hub small {
    font-size: .68rem !important;
  }
}

.av-img{width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;}
.farmer-avatar-sm,.chat-hdr-avatar{overflow:hidden;}

/* E-Certificate */

.cert-action-bar{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center;}
.cert-status-badge{display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .875rem;border-radius:9999px;font-size:.72rem;font-weight:800;flex-shrink:0;}
.cert-valid{background:#d1fae5;color:#065f46;border:2px solid #10b981;}
.cert-expired{background:#fee2e2;color:#991b1b;border:2px solid #ef4444;}

</style>
</head>
<body class="sidebar-autohide">

<div class="farmer-hover-zone" id="farmerHoverZone" aria-hidden="true"></div>
<div class="farmer-edge-trigger" id="farmerEdgeTrigger" title="Hover or click for navigation">
  <i class="bi bi-chevron-right"></i>
</div>

<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<aside class="drawer" id="drawer">
  <div class="drawer-hdr">
    <div class="drawer-brand-wrap">
      <div class="brand">Agri<span>Trace+</span></div>
      <span class="portal-lbl">Farmer Portal</span>
    </div>
    <button type="button" class="btn-drawer-pin" id="farmerPinBtn" title="Pin / Unpin Sidebar">
      <i class="bi bi-pin-angle"></i>
    </button>
    <button type="button" class="btn-drawer-close" onclick="closeDrawer()" aria-label="Close menu" title="Close Menu">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>
  <nav class="drawer-nav">
    <div class="drawer-item active" data-page="dashboard"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></div>
    
    <!-- REGISTRATION FORM DROPDOWN -->
    <div class="drawer-dropdown-btn" id="btn-drop-reg" onclick="toggleDrawerDropdown('drop-reg')" aria-expanded="false">
      <span style="display:flex;align-items:center;gap:.625rem;"><i class="bi bi-journal-text" style="font-size:1.05rem;width:1.125rem;text-align:center;"></i><span>Registration Form</span></span>
      <i class="bi bi-chevron-down drop-chevron"></i>
    </div>
    <div class="drawer-submenu" id="drop-reg">
      <div class="drawer-subitem" data-page="farm-reg">
        <span>Farm Registration</span>
        <?php if($farmRegStatus): ?>
          <span class="sub-status-badge badge-<?= $farmRegStatus ?>"><?= ucfirst($farmRegStatus) ?></span>
        <?php endif; ?>
      </div>
      <div class="drawer-subitem" data-page="rsbsa-form">
        <span>RSBSA Form</span>
        <?php if($rsbsaStatus): ?>
          <span class="sub-status-badge badge-<?= $rsbsaStatus ?>"><?= ucfirst($rsbsaStatus) ?></span>
        <?php endif; ?>
      </div>
      <div class="drawer-subitem" data-page="pcic-form">
        <span>PCIC Insurance Form</span>
        <?php if($pcicStatus): ?>
          <span class="sub-status-badge badge-<?= $pcicStatus ?>"><?= ucfirst($pcicStatus) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <div class="drawer-item" data-page="livestock"><i class="bi bi-clipboard2-pulse-fill"></i><span>Livestock &amp; Health</span></div>

    <!-- SERVICES DROPDOWN -->
    <div class="drawer-dropdown-btn" id="btn-drop-services" onclick="toggleDrawerDropdown('drop-services')" aria-expanded="false">
      <span style="display:flex;align-items:center;gap:.625rem;"><i class="bi bi-gear-wide-connected" style="font-size:1.05rem;width:1.125rem;text-align:center;"></i><span>Services</span></span>
      <i class="bi bi-chevron-down drop-chevron"></i>
    </div>
    <div class="drawer-submenu" id="drop-services">
      <div class="drawer-subitem" data-page="vet-request"><span>Request Assistance</span></div>
    </div>

    <div class="drawer-item" data-page="insurance"><i class="bi bi-shield-check"></i><span>PCIC Claims &amp; Info</span></div>
    <div class="drawer-item" data-page="ecert"><i class="bi bi-patch-check-fill"></i><span>Digital Certificates</span></div>
    <div class="drawer-item" data-page="incident"><i class="bi bi-exclamation-triangle-fill"></i><span>Report Incident</span></div>
    <div class="drawer-item" data-page="notifications"><i class="bi bi-bell-fill"></i><span>Notifications</span><span id="drawerNotifBadge" style="<?= $unreadCount>0?'':'display:none;' ?>background:var(--danger);color:#fff;border-radius:9999px;padding:.1rem .45rem;font-size:.65rem;font-weight:700;margin-left:auto;"><?= $unreadCount ?></span></div>
    <div class="drawer-item" data-page="chat"><i class="bi bi-chat-dots-fill"></i><span>Farmer Chat</span></div>
    <div class="drawer-item" data-page="profile"><i class="bi bi-person-circle"></i><span>My Profile</span></div>
    <div style="padding:.75rem 1.25rem;"><hr style="border:none;border-top:1px solid rgba(255,255,255,.1);"></div>
    <a href="logout.php" class="drawer-item" style="color:#fbbf24;"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
  </nav>
  <div class="drawer-footer">
    <div class="drawer-user">
      <div class="drawer-avatar"><?= avatar_inner($myPixUrl, $myInitial) ?></div>
      <div>
        <div style="font-size:.875rem;font-weight:700;color:#fff;"><?= htmlspecialchars($firstName.' '.$lastName) ?></div>
        <div style="font-size:.72rem;color:rgba(255,255,255,.6);">Farmer</div>
      </div>
    </div>
  </div>
</aside>

<!-- INCOMING CALL BANNER -->
<div class="incoming-call-banner" id="incomingCallBanner">
  <div style="font-size:1.5rem;"><i class="bi bi-telephone-fill"></i></div>
  <div class="incoming-info">
    <div class="incoming-title" id="incomingCallerName">Incoming Call</div>
    <div class="incoming-sub" id="incomingCallType">Voice Call</div>
  </div>
  <div class="incoming-btns">
    <button class="call-action-btn hangup" style="width:44px;height:44px;font-size:1.2rem;" onclick="rejectCall()"><i class="bi bi-telephone-x-fill"></i></button>
    <button class="call-action-btn answer" style="width:44px;height:44px;font-size:1.2rem;" onclick="answerCall()"><i class="bi bi-telephone-fill"></i></button>
  </div>
</div>

<!-- WEBRTC CALL OVERLAY -->
<div class="call-overlay" id="callOverlay">
  <div style="text-align:center;">
    <div class="call-overlay-avatar" id="callRemoteAvatar">?</div>
    <div class="call-overlay-name" id="callRemoteName">  -  </div>
    <div class="call-overlay-status" id="callStatus">Calling...</div>
    <div id="permErrorMsg" class="perm-error-banner" style="border-radius:12px;font-size:.8rem;padding:.625rem .875rem;margin-top:.75rem;text-align:left;display:none;">
      <i class="bi bi-mic-mute-fill" style="flex-shrink:0;"></i>
      <div>
        <strong>Microphone/Camera access denied.</strong><br>
        Please allow permissions in your browser settings and try again.
        <button onclick="cleanupCall()" style="display:block;margin-top:.5rem;background:var(--danger);color:#fff;border:none;padding:.3rem .75rem;border-radius:8px;font-size:.78rem;cursor:pointer;font-family:inherit;">Close Call</button>
      </div>
    </div>
  </div>
  <video class="call-remote-video" id="remoteVideo" autoplay playsinline></video>
  <video class="call-local-video" id="localVideo" autoplay playsinline muted></video>
  <div class="call-btn-row">
    <button class="call-action-btn mute" id="muteBtn" onclick="toggleMute()"><i class="bi bi-mic-fill"></i></button>
    <button class="call-action-btn hangup" onclick="hangupCall()"><i class="bi bi-telephone-x-fill"></i></button>
    <button class="call-action-btn cam-toggle" id="camToggleBtn" onclick="toggleCallCam()"><i class="bi bi-camera-video-fill"></i></button>
  </div>
</div>

<div class="app">
  <header class="top-bar">
    <button type="button" class="menu-btn" id="farmerMenuBtn" onclick="toggleDrawer()" aria-label="Open Navigation Menu" title="Open Menu"><i class="bi bi-list"></i></button>
    <div class="page-title" id="pageTitle">Dashboard</div>
    <div class="top-right">
      <button type="button" class="btn-top-req-assist" onclick="navTo('vet-request')" title="Request Veterinary Assistance" style="background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:10px;padding:.42rem .85rem;font-size:.78rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.38rem;box-shadow:0 2px 6px rgba(16,185,129,0.3);"><i class="bi bi-bandaid-fill"></i><span>Request Assistance</span></button>
      <button type="button" class="lang-switch-btn" onclick="toggleLanguage()" title="Palitan ang Wika / Switch Language"><span class="lang-flag"><i class="bi bi-translate"></i></span> <span class="lang-label">Tagalog</span></button>
      <button class="tb-bell <?= $unreadCount>0?'has-unread':'' ?>" id="topbarBellBtn" onclick="navTo('notifications')" aria-label="Notifications" title="Notifications"><i class="bi bi-bell-fill"></i><b id="topbarBellBadge" style="<?= $unreadCount>0?'':'display:none;' ?>"><?= (int)$unreadCount ?></b></button>
      <div class="tb-chip"><div class="tb-av"><?= avatar_inner($myPixUrl, $myInitial) ?></div><span><?= htmlspecialchars($firstName) ?></span></div>
    </div>
  </header>

  <main class="content-area" id="contentArea">
    <div class="content-inner">

      <!-- ===== DASHBOARD ===== -->
      <div id="pg-dashboard" class="page active">
<?php
  // -------------------------------- Dashboard view-model ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
  $hr = (int)date('G');
  $greetWord = $hr < 12 ? 'Good morning' : ($hr < 18 ? 'Good afternoon' : 'Good evening');

  $healthTotals = ['Healthy'=>0,'Sick'=>0,'Recovered'=>0,'Deceased'=>0];
  foreach ($allLvRows as $r) {
      if (empty($r['tagId'])) continue;
      $hs = $r['healthStatus'] ?? 'Healthy';
      if (!isset($healthTotals[$hs])) $hs = 'Healthy';
      $healthTotals[$hs] += max(1, (int)($r['qty'] ?? 1));
  }
  $healthSum  = array_sum($healthTotals);
  $healthyPct = $healthSum > 0 ? round($healthTotals['Healthy'] / $healthSum * 100) : 0;
  $healthColors = ['Healthy'=>'#10b981','Sick'=>'#ef4444','Recovered'=>'#3b82f6','Deceased'=>'#94a3b8'];
  $stops = []; $acc = 0;
  foreach ($healthTotals as $k => $v) {
      if ($healthSum <= 0 || $v <= 0) continue;
      $from = $acc; $acc += $v / $healthSum * 100;
      $stops[] = $healthColors[$k].' '.round($from,2).'% '.round($acc,2).'%';
  }
  $donutBg = $stops ? 'conic-gradient('.implode(',', $stops).')' : 'conic-gradient(#e2e8f0 0 100%)';

  $approvedFarms = count(array_filter($farmsData, fn($f) => $f['status'] === 'Approved'));
  $activeCerts   = count(array_filter($certsData, fn($c) => ($c['status'] ?? '') === 'Active'));
  $animalColors  = ['Cattle'=>'#f59e0b','Swine'=>'#ec4899','Poultry'=>'#f97316','Goat'=>'#8b5cf6','Carabao'=>'#64748b','Horse'=>'#92400e','Sheep'=>'#0ea5e9','Rabbit'=>'#14b8a6','Duck'=>'#06b6d4','Turkey'=>'#d97706'];
  $animalEmojis  = ['Cattle'=>'🐄','Swine'=>'🐖','Poultry'=>'🐓','Goat'=>'🐐','Carabao'=>'🐃','Horse'=>'🐴','Sheep'=>'🐑','Rabbit'=>'🐇','Duck'=>'🦆','Turkey'=>'🦃'];
  $maxType = !empty($lvByType) ? max($lvByType) : 1;
  $recentIncidents = array_slice($incidentsData, 0, 4);
  $recentNotifs    = array_slice($notificationsData, 0, 4);
?>
        <div class="dash">

          <!-- HERO -->
          <section class="d-hero">
            <div class="d-hero-main">
              <span class="d-hero-date"><i class="bi bi-calendar3"></i> <?= date('l, F j, Y') ?></span>
              <h1><?= $greetWord ?>, <?= htmlspecialchars($firstName) ?>!</h1>
              <p>Here's what's happening across your farms today.</p>
              <div class="d-hero-actions">
                <button class="d-btn d-btn-light" onclick="navTo('vet-request')"><i class="bi bi-bandaid-fill"></i> Request Assistance</button>
                <button class="d-btn d-btn-ghost" onclick="navTo('farm-reg')"><i class="bi bi-house-add-fill"></i> Register Farm</button>
                <button class="d-btn d-btn-ghost" onclick="navTo('livestock')"><i class="bi bi-clipboard2-pulse-fill"></i> Livestock</button>
                <button class="d-btn d-btn-ghost" onclick="navTo('insurance')"><i class="bi bi-shield-check"></i> PCIC Insurance</button>
                <button class="d-btn d-btn-ghost" onclick="navTo('vet-request'); setAssistanceType('Slaughter Assistance');"><i class="bi bi-receipt-cutoff"></i> Slaughter</button>
                <button class="d-btn d-btn-ghost" onclick="navTo('ecert')"><i class="bi bi-patch-check-fill"></i> Certificates</button>
              </div>
            </div>
            <div class="d-hero-side">
              <div class="d-hero-ring" style="--p:<?= (int)$healthyPct ?>;"><div><strong><?= (int)$healthyPct ?>%</strong><span>Healthy</span></div></div>
              <div class="d-hero-side-txt">
                <strong><?= number_format($totalLivestock) ?></strong>
                <span>animals across <?= count($farmsData) ?> farm<?= count($farmsData)===1?'':'s' ?></span>
              </div>
            </div>
          </section>

          <!-- KPI CARDS -->
          <section class="d-kpis">
            <div class="d-kpi">
              <div class="d-kpi-ico" style="--c:#10b981;"><i class="bi bi-house-heart-fill"></i></div>
              <div class="d-kpi-body"><span>My Farms</span><strong><?= count($farmsData) ?></strong><small><?= $approvedFarms ?> approved</small></div>
            </div>
            <div class="d-kpi">
              <div class="d-kpi-ico" style="--c:#3b82f6;"><i class="bi bi-clipboard2-pulse-fill"></i></div>
              <div class="d-kpi-body"><span>Total Livestock</span><strong><?= number_format($totalLivestock) ?></strong><small><?= count($lvByType) ?> animal type<?= count($lvByType)===1?'':'s' ?></small></div>
            </div>
            <div class="d-kpi">
              <div class="d-kpi-ico" style="--c:#ef4444;"><i class="bi bi-exclamation-triangle-fill"></i></div>
              <div class="d-kpi-body"><span>Active Incidents</span><strong><?= $activeIncidents ?></strong><small><?= $activeIncidents ? 'Needs follow-up' : 'All clear' ?></small></div>
            </div>
            <div class="d-kpi">
              <div class="d-kpi-ico" style="--c:#f59e0b;"><i class="bi bi-hourglass-split"></i></div>
              <div class="d-kpi-body"><span>Pending Approvals</span><strong><?= $pendingFarms ?></strong><small><?= $activeCerts ?> active certificate<?= $activeCerts===1?'':'s' ?></small></div>
            </div>
          </section>

          <!-- QUICK SERVICES HUB -->
          <section class="farmer-services-hub" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;margin-bottom:1.5rem;">
            <div onclick="navTo('vet-request')" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1rem;cursor:pointer;display:flex;align-items:center;gap:.75rem;transition:transform .15s,box-shadow .15s;box-shadow:0 1px 3px rgba(0,0,0,.04);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
              <div style="width:42px;height:42px;border-radius:10px;background:#ecfdf5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:1.25rem;"><i class="bi bi-bandaid-fill"></i></div>
              <div>
                <strong style="display:block;font-size:.86rem;color:#1e293b;">Request Assistance</strong>
                <small style="color:#64748b;font-size:.72rem;">Vet &amp; treatment</small>
              </div>
            </div>
            <div onclick="navTo('livestock')" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1rem;cursor:pointer;display:flex;align-items:center;gap:.75rem;transition:transform .15s,box-shadow .15s;box-shadow:0 1px 3px rgba(0,0,0,.04);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
              <div style="width:42px;height:42px;border-radius:10px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:1.25rem;"><i class="bi bi-heart-pulse-fill"></i></div>
              <div>
                <strong style="display:block;font-size:.86rem;color:#1e293b;">Health Monitoring</strong>
                <small style="color:#64748b;font-size:.72rem;">History &amp; checkups</small>
              </div>
            </div>
            <div onclick="navTo('insurance')" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1rem;cursor:pointer;display:flex;align-items:center;gap:.75rem;transition:transform .15s,box-shadow .15s;box-shadow:0 1px 3px rgba(0,0,0,.04);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
              <div style="width:42px;height:42px;border-radius:10px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:1.25rem;"><i class="bi bi-shield-check"></i></div>
              <div>
                <strong style="display:block;font-size:.86rem;color:#1e293b;">PCIC Insurance</strong>
                <small style="color:#64748b;font-size:.72rem;">Referrals &amp; claims</small>
              </div>
            </div>
            <div onclick="navTo('vet-request'); setAssistanceType('Slaughter Assistance');" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1rem;cursor:pointer;display:flex;align-items:center;gap:.75rem;transition:transform .15s,box-shadow .15s;box-shadow:0 1px 3px rgba(0,0,0,.04);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
              <div style="width:42px;height:42px;border-radius:10px;background:#fdf2f8;color:#db2777;display:flex;align-items:center;justify-content:center;font-size:1.25rem;"><i class="bi bi-receipt-cutoff"></i></div>
              <div>
                <strong style="display:block;font-size:.86rem;color:#1e293b;">Slaughter Decl.</strong>
                <small style="color:#64748b;font-size:.72rem;">Legal reporting</small>
              </div>
            </div>
            <div onclick="navTo('ecert')" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1rem;cursor:pointer;display:flex;align-items:center;gap:.75rem;transition:transform .15s,box-shadow .15s;box-shadow:0 1px 3px rgba(0,0,0,.04);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
              <div style="width:42px;height:42px;border-radius:10px;background:#f0fdf4;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:1.25rem;"><i class="bi bi-patch-check-fill"></i></div>
              <div>
                <strong style="display:block;font-size:.86rem;color:#1e293b;">Digital Certificates</strong>
                <small style="color:#64748b;font-size:.72rem;">Upload &amp; monitor</small>
              </div>
            </div>
          </section>

          <div class="d-grid">
            <!-- LEFT COLUMN -->
            <div class="d-col">

              <div class="d-card">
                <div class="d-card-hd">
                  <div><h3>Livestock by Type</h3><p>Distribution of your herd</p></div>
                  <button class="d-link" onclick="navTo('livestock')">Manage <i class="bi bi-arrow-right"></i></button>
                </div>
                <?php if(!empty($lvByType)): ?>
                <div class="d-bars">
                  <?php foreach($lvByType as $aType => $aCount):
                    $pct = $totalLivestock > 0 ? round($aCount / $totalLivestock * 100) : 0;
                    $w   = max(4, round($aCount / $maxType * 100));
                    $col = $animalColors[$aType] ?? $animalColors[ucfirst(strtolower((string)$aType))] ?? '#10b981';
                    $ico = $animalEmojis[$aType] ?? $animalEmojis[ucfirst(strtolower((string)$aType))] ?? '🐾'; ?>
                  <div class="d-bar-row">
                    <div class="d-bar-ico" style="--c:<?= $col ?>;"><?= $ico ?></div>
                    <div class="d-bar-main">
                      <div class="d-bar-top"><span><?= htmlspecialchars($aType) ?></span><span><strong><?= number_format($aCount) ?></strong> <em><?= $pct ?>%</em></span></div>
                      <div class="d-bar-track"><div class="d-bar-fill" style="width:<?= $w ?>%;background:<?= $col ?>;"></div></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="d-empty"><i class="bi bi-clipboard2-pulse"></i><h4>No livestock recorded</h4><p>Register a farm and add animals to see your herd breakdown.</p></div>
                <?php endif; ?>
              </div>

              <div class="d-card">
                <div class="d-card-hd">
                  <div><h3>My Farms</h3><p><?= count($farmsData) ?> registered</p></div>
                  <button class="d-btn d-btn-primary d-btn-sm" onclick="navTo('farm-reg')"><i class="bi bi-plus-lg"></i> Add Farm</button>
                </div>
                <?php if(!empty($farmsData)): ?>
                <div class="d-farms">
                  <?php foreach($farmsData as $farm):
                    $fHeads = 0;
                    if (isset($livestockByFarm[$farm['id']])) foreach ($livestockByFarm[$farm['id']]['livestock'] as $lvr) $fHeads += max(1,(int)($lvr['qty'] ?? 1));
                    $addr = trim((string)($farm['address'] ?? '')); ?>
                  <div class="d-farm">
                    <div class="d-farm-av"><?= strtoupper(substr($farm['name'],0,1)) ?></div>
                    <div class="d-farm-info">
                      <div class="d-farm-name"><?= htmlspecialchars($farm['name']) ?></div>
                      <div class="d-farm-meta">
                        <span><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($farm['type']) ?></span>
                        <span><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($addr !== '' ? (strlen($addr) > 34 ? substr($addr,0,34).'...' : $addr) : 'No address') ?></span>
                        <span><i class="bi bi-clipboard2-pulse-fill"></i> <?= number_format($fHeads) ?> head</span>
                      </div>
                    </div>
                    <span class="badge <?= statusBadge($farm['status']) ?>"><?= htmlspecialchars($farm['status']) ?></span>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="d-empty"><i class="bi bi-house"></i><h4>No farms yet</h4><p>Register your first farm to get started.</p>
                  <button class="d-btn d-btn-primary" style="margin-top:1rem;" onclick="navTo('farm-reg')"><i class="bi bi-house-add-fill"></i> Register Farm</button></div>
                <?php endif; ?>
              </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="d-col">

              <div class="d-card">
                <div class="d-card-hd"><div><h3>Herd Health</h3><p>Current condition of your animals</p></div></div>
                <?php if($healthSum > 0): ?>
                <div class="d-health">
                  <div class="d-donut" style="background:<?= $donutBg ?>;"><div><strong><?= (int)$healthyPct ?>%</strong><span>Healthy</span></div></div>
                  <ul class="d-legend">
                    <?php foreach($healthTotals as $k => $v): ?>
                    <li><i style="background:<?= $healthColors[$k] ?>;"></i><span><?= $k ?></span><strong><?= number_format($v) ?></strong></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <?php else: ?>
                <div class="d-empty"><i class="bi bi-heart-pulse"></i><h4>No health data</h4><p>Add livestock records to track herd health.</p></div>
                <?php endif; ?>
              </div>

              <div class="d-card">
                <div class="d-card-hd">
                  <div><h3>Recent Incidents</h3><p>Your latest reports</p></div>
                  <button class="d-link" onclick="navTo('incident')">View all <i class="bi bi-arrow-right"></i></button>
                </div>
                <?php if(!empty($recentIncidents)): ?>
                <div class="d-list">
                  <?php foreach($recentIncidents as $inc): $res = $inc['status']==='Resolved'; ?>
                  <div class="d-item">
                    <div class="d-item-ico" style="--c:<?= $res ? '#10b981' : '#ef4444' ?>;"><i class="bi bi-<?= $res ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i></div>
                    <div class="d-item-body">
                      <div class="d-item-t"><?= htmlspecialchars($inc['title']) ?></div>
                      <div class="d-item-s"><?= htmlspecialchars($inc['farmName'] ?? 'Farm') ?> &bull; <?= date('M d, Y', strtotime($inc['createdAt'])) ?></div>
                    </div>
                    <span class="badge <?= statusBadge($inc['status']) ?>"><?= htmlspecialchars($inc['status']) ?></span>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="d-empty"><i class="bi bi-shield-check"></i><h4>No incidents</h4><p>Your farms are looking good.</p></div>
                <?php endif; ?>
              </div>

              <div class="d-card">
                <div class="d-card-hd">
                  <div><h3>Notifications</h3><p id="dashNotifUnreadText"><?= $unreadCount ?> unread</p></div>
                  <button class="d-link" onclick="navTo('notifications')">View all <i class="bi bi-arrow-right"></i></button>
                </div>
                <?php if(!empty($recentNotifs)): ?>
                <div class="d-list" id="dashNotifFeed">
                  <?php foreach($recentNotifs as $notif): $crit = in_array($notif['type'],['CRITICAL_ALERT','OUTBREAK_WARNING']); ?>
                  <div class="d-item" id="dash-item-<?= $notif['id'] ?>" onclick="navTo('notifications'); markNotifRead(<?= $notif['id'] ?>);" style="cursor:pointer;" title="Click to view notification">
                    <div class="d-item-ico" style="--c:<?= $crit ? '#ef4444' : '#10b981' ?>;"><i class="bi bi-<?= $crit ? 'exclamation-triangle-fill' : 'bell-fill' ?>"></i></div>
                    <div class="d-item-body">
                      <div class="d-item-t"><?= htmlspecialchars($notif['title']) ?></div>
                      <div class="d-item-s"><?= htmlspecialchars(substr($notif['message'],0,60)).(strlen($notif['message'])>60?'...':'') ?></div>
                    </div>
                    <?php if(empty($notif['is_read'])): ?><span class="d-dot" id="dash-dot-<?= $notif['id'] ?>"></span><?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="d-empty"><i class="bi bi-bell-slash"></i><h4>No notifications yet</h4></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ===== FARM REGISTRATION ===== -->
      <div id="pg-farm-reg" class="page">
        <?php if($latestFarmReg): ?>
        <div class="form-status-banner banner-<?= $farmRegStatus ?> no-print">
          <div>
            <b>Registration Status: <?= ucfirst($latestFarmReg['status']) ?></b> &bull; Farm: <b><?= htmlspecialchars($latestFarmReg['farm_name'] ?? 'N/A') ?></b>
            <div style="font-size:.8rem;opacity:.9;margin-top:2px;">Submitted on <?= date('M d, Y h:i A', strtotime($latestFarmReg['created_at'])) ?>
            <?php if(!empty($latestFarmReg['remarks'])): ?>
              &bull; <i>Remarks: <?= htmlspecialchars($latestFarmReg['remarks']) ?></i>
            <?php endif; ?>
            </div>
          </div>
          <span class="sub-status-badge badge-<?= $farmRegStatus ?>"><?= ucfirst($farmRegStatus) ?></span>
        </div>
        <?php endif; ?>
        <div class="section-hdr">
          <div class="section-seal"><i class="bi bi-shield-check" style="color:var(--acc);font-size:2rem;"></i></div>
          <div>
            <h2>Farm Registration</h2>
            <p>DA Camarines Sur &bull; RA 8485 Animal Welfare Act<br>Official Digital Farm Accreditation &amp; Biosecurity Registry</p>
          </div>
        </div>
        <div class="toggle-row">
          <button type="button" class="tog-btn active" id="btnNew" onclick="setAppType('new')"><i class="bi bi-file-earmark-plus"></i> New</button>
          <button type="button" class="tog-btn" id="btnRenew" onclick="setAppType('renewal')"><i class="bi bi-arrow-repeat"></i> Renewal</button>
          <button type="button" class="tog-btn" id="btnGovt" onclick="setOwnership('government')"><i class="bi bi-building"></i> Govt</button>
          <button type="button" class="tog-btn active" id="btnPriv" onclick="setOwnership('private')"><i class="bi bi-person"></i> Private</button>
        </div>
        <form id="farmForm" autocomplete="off">
          <input type="hidden" name="action" value="register_farm">
          <input type="hidden" id="renewFarmId" name="renew_farm_id" value="">
          <input type="hidden" id="fPhoto" name="photo_data">
          <input type="hidden" id="fLat" name="farm_lat">
          <input type="hidden" id="fLng" name="farm_lng">
          <input type="hidden" id="fGpsAcc" name="gps_accuracy">
          <input type="hidden" id="fGpsSource" name="gps_source">
          <input type="hidden" id="appTypeVal" name="app_type" value="New">
          <input type="hidden" id="ownershipVal" name="ownership" value="Private">
          <input type="hidden" id="facilityTypesVal" name="facility_types">
          <input type="hidden" id="bizTypeVal" name="business_type" value="Sole Proprietor">

          <!-- RENEWAL FARM SELECTOR (shown when Renewal is active) -->
          <div id="renewFarmSelectWrap" style="display:none;margin-bottom:1.25rem;background:#eef2ff;border:1.5px solid #c7d2fe;border-radius:14px;padding:1rem 1.15rem;">
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem;">
              <i class="bi bi-arrow-repeat" style="font-size:1.15rem;color:#4f46e5;"></i>
              <strong style="font-size:.88rem;color:#3730a3;">Select Registered Farm to Renew</strong>
            </div>
            <p style="font-size:.78rem;color:#4338ca;margin-bottom:.75rem;line-height:1.4;">
              Renewing an existing farm updates its records and certificate in the database without creating duplicate registrations.
            </p>
            <?php if(!empty($farmsData)): ?>
            <select class="form-select" id="renewFarmSelect" onchange="populateRenewFarm(this.value)" style="background:#ffffff;border-color:#a5b4fc;font-weight:600;">
              <option value="">-- Choose an existing farm to renew --</option>
              <?php foreach($farmsData as $rf): ?>
              <option value="<?= $rf['id'] ?>" data-farm="<?= htmlspecialchars(json_encode($rf), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($rf['name']) ?> (<?= $rf['type'] ?>, <?= htmlspecialchars($rf['pet_municipality'] ?? $rf['address'] ?? '') ?>) [Status: <?= $rf['status'] ?>]
              </option>
              <?php endforeach; ?>
            </select>
            <?php else: ?>
            <div style="font-size:.8rem;color:#6b7280;font-style:italic;">
              No registered farms found in your account yet. You can type your farm name below to link matching records.
            </div>
            <?php endif; ?>
          </div>

          <!-- SECTION I -->
          <div class="form-section">
            <div class="form-section-hdr"><span class="form-section-num">I</span> Petitioner Information</div>
            <div class="form-section-body">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group"><label class="form-label">Surname *</label><input type="text" class="form-input" name="pet_surname" placeholder="Last name" required></div>
                <div class="form-group"><label class="form-label">First Name *</label><input type="text" class="form-input" name="pet_firstname" value="<?= htmlspecialchars($firstName) ?>" required></div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group"><label class="form-label">Middle Name</label><input type="text" class="form-input" name="pet_middle" placeholder="Middle name"></div>
                <div class="form-group"><label class="form-label">Extension Name</label><input type="text" class="form-input" name="pet_ext" placeholder="Jr., Sr., III..."></div>
              </div>
              <div class="form-group"><label class="form-label">Sex *</label>
                <div class="biz-row" id="sexRow">
                  <button type="button" class="biz-pill" onclick="toggleSex(this,'Male')">Male</button>
                  <button type="button" class="biz-pill" onclick="toggleSex(this,'Female')">Female</button>
                </div>
                <input type="hidden" id="petSexVal" name="pet_sex" value="">
              </div>
              <div class="form-group"><label class="form-label">House/Lot/Bldg No.</label><input type="text" class="form-input" name="pet_house_no" placeholder="House/Lot/Bldg No."></div>
              <div class="form-group"><label class="form-label">Street / Sitio / Subdv.</label><input type="text" class="form-input" name="pet_street" placeholder="Street / Sitio / Subdv."></div>
              <div class="form-group"><label class="form-label">Barangay *</label><input type="text" class="form-input" name="pet_barangay" placeholder="Barangay" required></div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group"><label class="form-label">Municipality/City *</label><input type="text" class="form-input" name="pet_municipality" placeholder="Municipality/City" required></div>
                <div class="form-group"><label class="form-label">Province</label><input type="text" class="form-input" name="pet_province" value="Camarines Sur" readonly></div>
              </div>
              <div class="form-group"><label class="form-label">Region</label><input type="text" class="form-input" name="pet_region" value="Region V   -   Bicol Region" readonly></div>
              <div class="form-group"><label class="form-label">Contact Number *</label><input type="tel" class="form-input" name="pet_phone" placeholder="+63 9XX XXX XXXX" value="<?= htmlspecialchars($profileData['mobile']??'') ?>" required></div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group"><label class="form-label">Date of Birth *</label><input type="date" class="form-input" name="pet_dob" value="<?= htmlspecialchars($profileData['dob']??'') ?>" required></div>
                <div class="form-group"><label class="form-label">Place of Birth</label><input type="text" class="form-input" name="pet_pob" placeholder="City/Municipality"></div>
              </div>
              <div class="form-group"><label class="form-label">Civil Status *</label>
                <select class="form-select" name="pet_civil_status" required>
                  <option value="">Select...</option>
                  <option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option>
                </select>
              </div>
              <div class="form-group"><label class="form-label">Spouse's Name (if married)</label><input type="text" class="form-input" name="pet_spouse" placeholder="Full name of spouse"></div>
              <div class="form-group"><label class="form-label">Mother's Maiden Name</label><input type="text" class="form-input" name="pet_mothers_name" placeholder="Mother's maiden name"></div>
              <div class="form-group"><label class="form-label">Highest Formal Education</label>
                <select class="form-select" name="pet_education">
                  <option value="">Select...</option>
                  <option>None</option><option>Elementary</option><option>High School</option>
                  <option>Vocational</option><option>College</option><option>Post Graduate</option>
                </select>
              </div>
              <div class="form-group"><label class="form-label">Religion</label><input type="text" class="form-input" name="pet_religion" placeholder="Religion"></div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group"><label class="form-label">Male Household Members</label><input type="number" class="form-input" name="pet_male_members" value="0" min="0"></div>
                <div class="form-group"><label class="form-label">Female Household Members</label><input type="number" class="form-input" name="pet_female_members" value="0" min="0"></div>
              </div>
              <div class="form-group"><label class="form-label">Household Head?</label>
                <div class="biz-row"><button type="button" class="biz-pill" onclick="togglePill(this,'hh_head','Yes')">Yes</button><button type="button" class="biz-pill" onclick="togglePill(this,'hh_head','No')">No</button></div>
                <input type="hidden" id="hhHeadVal" name="hh_head" value="No">
              </div>
              <div class="form-group"><label class="form-label">If no, household head name &amp; relationship</label><input type="text" class="form-input" name="hh_head_name" placeholder="Name   -   Relationship"></div>
              <div class="form-group"><label class="form-label">Person with Disability (PWD)?</label>
                <div class="biz-row"><button type="button" class="biz-pill" onclick="togglePill(this,'pwd','Yes')">Yes</button><button type="button" class="biz-pill" onclick="togglePill(this,'pwd','No')">No</button></div>
                <input type="hidden" id="pwdVal" name="pwd" value="No">
              </div>
              <div class="form-group"><label class="form-label">4P's Beneficiary?</label>
                <div class="biz-row"><button type="button" class="biz-pill" onclick="togglePill(this,'fourps','Yes')">Yes</button><button type="button" class="biz-pill" onclick="togglePill(this,'fourps','No')">No</button></div>
                <input type="hidden" id="fourpsVal" name="fourps" value="No">
              </div>
              <div class="form-group"><label class="form-label">Member of Indigenous Group?</label>
                <div class="biz-row"><button type="button" class="biz-pill" onclick="togglePill(this,'indigenous','Yes')">Yes</button><button type="button" class="biz-pill" onclick="togglePill(this,'indigenous','No')">No</button></div>
                <input type="hidden" id="indigenousVal" name="indigenous" value="No">
                <input type="text" class="form-input" name="indigenous_specify" placeholder="If yes, specify..." style="margin-top:.4rem;">
              </div>
              <div class="form-group"><label class="form-label">With Government ID?</label>
                <div class="biz-row"><button type="button" class="biz-pill" onclick="togglePill(this,'gov_id','Yes')">Yes</button><button type="button" class="biz-pill" onclick="togglePill(this,'gov_id','No')">No</button></div>
                <input type="hidden" id="govIdVal" name="has_gov_id" value="No">
                <input type="text" class="form-input" name="gov_id_number" placeholder="Specify ID number if yes" style="margin-top:.4rem;">
              </div>
              <div class="form-group"><label class="form-label">Member of Farmers Association / Cooperative?</label>
                <div class="biz-row"><button type="button" class="biz-pill" onclick="togglePill(this,'farmers_assoc','Yes')">Yes</button><button type="button" class="biz-pill" onclick="togglePill(this,'farmers_assoc','No')">No</button></div>
                <input type="hidden" id="farmersAssocVal" name="farmers_assoc" value="No">
                <input type="text" class="form-input" name="farmers_assoc_name" placeholder="If yes, specify association..." style="margin-top:.4rem;">
              </div>
              <div class="form-group"><label class="form-label">Emergency Contact Person</label><input type="text" class="form-input" name="emergency_contact_name" value="<?= htmlspecialchars($profileData['emergency_contact']??'') ?>" placeholder="Full name"></div>
              <div class="form-group"><label class="form-label">Emergency Contact Number</label><input type="tel" class="form-input" name="emergency_contact_no" placeholder="+63 9XX XXX XXXX"></div>
            </div>
          </div>

          <!-- SECTION II -->
          <div class="form-section">
            <div class="form-section-hdr"><span class="form-section-num">II</span> Farm Profile &amp; Main Livelihood</div>
            <div class="form-section-body">
              <div class="form-group"><label class="form-label">Farm Name *</label><input type="text" class="form-input" name="farm_name" placeholder="e.g. Dela Cruz Livestock Farm" required></div>
              <div class="form-group"><label class="form-label">Farm Address *</label><input type="text" class="form-input" id="farmAddress" name="farm_address" placeholder="Complete farm address" required></div>
              <div class="form-group"><label class="form-label">Farm Type *</label>
                <select class="form-select" name="farm_type" required>
                  <option value="Mixed">Mixed</option>
                  <option value="Cattle">Cattle</option>
                  <option value="Swine">Swine</option>
                  <option value="Poultry">Poultry</option>
                  <option value="Goat">Goat</option>
                </select>
              </div>
              <div class="form-group"><label class="form-label">Farm Area (hectares)</label><input type="number" class="form-input" name="farm_area" placeholder="0.00" step="0.01" min="0"></div>
              <div class="form-group"><label class="form-label">Business Type</label>
                <div class="biz-row" id="bizRow">
                  <button type="button" class="biz-pill active" onclick="toggleBiz(this)">Sole Proprietor</button>
                  <button type="button" class="biz-pill" onclick="toggleBiz(this)">Partnership</button>
                  <button type="button" class="biz-pill" onclick="toggleBiz(this)">Corporation</button>
                  <button type="button" class="biz-pill" onclick="toggleBiz(this)">Cooperative</button>
                </div>
              </div>
              <div class="form-group"><label class="form-label">Main Livelihood *</label>
                <div class="biz-row" id="livelihoodRow">
                  <button type="button" class="biz-pill active" onclick="toggleLivelihood(this,'Farmer')">Farmer</button>
                  <button type="button" class="biz-pill" onclick="toggleLivelihood(this,'Farmworker/Laborer')">Farmworker/Laborer</button>
                  <button type="button" class="biz-pill" onclick="toggleLivelihood(this,'Fisherfolk')">Fisherfolk</button>
                </div>
                <input type="hidden" id="livelihoodVal" name="main_livelihood" value="Farmer">
              </div>
              <div class="form-group"><label class="form-label">Type of Farming Activity</label>
                <div class="cb-grid" id="farmingTypeCbs">
                  <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="farming_rice" value="1"> Rice</label>
                  <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="farming_corn" value="1"> Corn</label>
                  <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="farming_livestock" value="1"> Livestock</label>
                  <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="farming_poultry" value="1"> Poultry</label>
                  <label class="cb-item" onclick="syncFarmCb(this)" style="grid-column:1/-1"><input type="checkbox" name="farming_other" value="1"> Other crops</label>
                </div>
                <input type="text" class="form-input" name="farming_other_specify" placeholder="Other crops   -   please specify" style="margin-top:.4rem;">
              </div>
              <div class="form-group"><label class="form-label">Livestock Details</label><input type="text" class="form-input" name="livestock_specify" placeholder="e.g. Cattle, Swine, Goat..."></div>
              <div class="form-group"><label class="form-label">Poultry Details</label><input type="text" class="form-input" name="poultry_specify" placeholder="e.g. Broiler, Layer, Native..."></div>
              <div class="form-group"><label class="form-label">Gross Annual Income Last Year</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div><label class="form-label" style="font-size:.72rem;">Farming (PHP )</label><input type="number" class="form-input" name="income_farming" placeholder="0.00" min="0" step="0.01"></div>
                  <div><label class="form-label" style="font-size:.72rem;">Non-farming (PHP )</label><input type="number" class="form-input" name="income_non_farming" placeholder="0.00" min="0" step="0.01"></div>
                </div>
              </div>
              <div class="gps-zone">
                <h4><i class="bi bi-camera-video"></i> Farm Photo + GPS Location</h4>
                <div class="photo-actions">
                  <button type="button" class="btn-cam primary" onclick="openFarmCam()"><i class="bi bi-camera-fill"></i> Take Photo</button>
                  <label class="btn-cam secondary" style="cursor:pointer;">
                    <i class="bi bi-upload"></i> Upload
                    <input type="file" accept="image/*" style="display:none;" onchange="handleFarmUpload(event)">
                  </label>
                </div>
                <div style="margin-top:.5rem;">
                  <button type="button" class="gps-picker-btn" onclick="openLocationPicker('farm', null, 'Drag the marker to your exact farm location inside Camarines Sur 1st District.')"><i class="bi bi-pin-map-fill" style="color:#059669;"></i> Pin / Refine on Map</button>
                </div>
                <div class="gps-badge" id="farmGpsBadge"><i class="bi bi-geo-alt-fill"></i> <span id="farmGpsText">GPS Captured</span></div>
                <div class="gps-warn-box" id="farmGpsWarn"></div>
                <div class="gps-picker-wrap" id="farmMapPickerWrap">
                  <div class="gps-picker-hdr">
                    <strong><i class="bi bi-pin-map-fill" style="color:#059669;"></i> Pin Exact Farm Location <span id="farmMapEngineBadge" style="font-size:0.68rem;padding:2px 7px;border-radius:10px;background:#e0f2fe;color:#0284c7;font-weight:700;display:inline-flex;align-items:center;gap:3px;"><i class="bi bi-google"></i> Google Maps</span></strong>
                    <div class="gps-picker-btns">
                      <button type="button" class="gps-picker-btn" onclick="togglePickerBasemap('farm')"><i class="bi bi-layers"></i> <span id="farmBasemapLbl">Street</span></button>
                      <button type="button" class="gps-picker-btn" onclick="pickerUseMyLocation('farm')"><i class="bi bi-crosshair"></i> Use my location</button>
                    </div>
                  </div>
                  <div class="gps-picker-search-bar">
                    <div style="position:relative;flex:1;">
                      <i class="bi bi-search" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.8rem;"></i>
                      <input type="text" id="farmMapSearchInput" class="gps-picker-search-input" placeholder="Search municipality, barangay, or landmark..." onkeydown="if(event.key==='Enter'){event.preventDefault();searchPickerLocation('farm');}">
                    </div>
                    <button type="button" class="gps-picker-btn gps-picker-search-btn" onclick="searchPickerLocation('farm')"><i class="bi bi-search"></i> Search</button>
                  </div>
                  <div class="gps-picker-chips">
                    <span style="color:#64748b;font-weight:600;white-space:nowrap;font-size:0.72rem;"><i class="bi bi-geo"></i> 1st District:</span>
                    <button type="button" class="gps-chip-btn" onclick="jumpPickerTo('farm', 13.8189, 122.7911, 'Ragay')">Ragay</button>
                    <button type="button" class="gps-chip-btn" onclick="jumpPickerTo('farm', 13.9167, 122.6000, 'Del Gallego')">Del Gallego</button>
                    <button type="button" class="gps-chip-btn" onclick="jumpPickerTo('farm', 13.7700, 122.9800, 'Sipocot')">Sipocot</button>
                    <button type="button" class="gps-chip-btn" onclick="jumpPickerTo('farm', 13.8800, 122.8700, 'Lupi')">Lupi</button>
                    <button type="button" class="gps-chip-btn" onclick="jumpPickerTo('farm', 13.7167, 123.0167, 'Cabusao')">Cabusao</button>
                  </div>
                  <div style="position:relative;">
                    <div class="gps-map-hint"><i class="bi bi-hand-index-thumb"></i> Tap map or drag pin to relocate</div>
                    <div id="farmPickerMap" class="gps-picker-map"></div>
                  </div>
                  <div class="gps-picker-bar">
                    <span class="gps-picker-coords" id="farmPickerCoords">Drag marker to farm</span>
                    <div class="gps-picker-btns">
                      <button type="button" class="gps-picker-btn" onclick="closeLocationPicker('farm')">Close</button>
                      <button type="button" class="gps-picker-btn prim" onclick="confirmLocationPin('farm')"><i class="bi bi-check-lg"></i> Confirm Pin</button>
                    </div>
                  </div>
                </div>
                <div class="photo-preview" id="farmPhotoPreview">
                  <canvas id="farmStampCanvas"></canvas>
                  <button type="button" class="remove-photo" onclick="removeFarmPhoto()">&times;</button>
                </div>
              </div>
            </div>
          </div>

          <!-- SECTION III: Dynamic Livestock -->
          <div class="form-section">
            <div class="form-section-hdr"><span class="form-section-num">III</span> Livestock Inventory</div>
            <div class="form-section-body">
              <p style="font-size:.78rem;color:var(--sub);margin-bottom:1rem;">Tap "Add Animal Type" to add livestock. Only enter the types you actually have.</p>

              <!-- Dynamic rows container -->
              <div class="lv-dynamic-list" id="lvDynamicList"></div>

              <!-- Animal picker (hidden by default) -->
              <div class="animal-picker-wrap" id="animalPickerWrap">
                <p style="font-size:.78rem;font-weight:700;color:var(--sub);margin-bottom:.625rem;text-transform:uppercase;letter-spacing:.05em;">Select animal type</p>
                <div class="animal-picker">
                  <?php foreach($allAnimalTypes as $at): ?>
                  <button type="button" class="animal-pick-btn" onclick="addAnimalRow('<?= $at ?>')" data-type="<?= $at ?>">
                    <span><?= $animalEmoji[$at] ?></span><?= $at ?>
                  </button>
                  <?php endforeach; ?>
                </div>
                <button type="button" onclick="toggleAnimalPicker()" style="width:100%;margin-top:.75rem;padding:.5rem;border:none;background:none;color:var(--sub);font-size:.82rem;font-weight:600;cursor:pointer;">Cancel</button>
              </div>

              <button type="button" class="btn-add-animal" id="btnAddAnimal" onclick="toggleAnimalPicker()">
                <i class="bi bi-plus-circle-fill"></i> Add Animal Type
              </button>
            </div>
          </div>

          <button type="submit" class="btn-submit" id="farmSubmitBtn">
            <i class="bi bi-send-fill"></i> Submit &amp; Get Certificate
          </button>
        </form>
      </div>


      <!-- ===== RSBSA ENROLLMENT FORM ===== -->
      <div id="pg-rsbsa-form" class="page">
        <div class="page-top-actions no-print" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;background:#fff;padding:.875rem 1.25rem;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.05);">
          <div style="display:flex;align-items:center;gap:.75rem;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="navTo('dashboard')"><i class="bi bi-arrow-left"></i> Dashboard</button>
            <h3 style="margin:0;font-size:1rem;font-weight:700;color:var(--pri);">RSBSA Enrollment Form</h3>
            <?php if($rsbsaStatus): ?>
              <span class="sub-status-badge badge-<?= $rsbsaStatus ?>" style="font-size:.75rem;padding:.2rem .6rem;"><?= ucfirst($rsbsaStatus) ?></span>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:.5rem;">
               <button type="button" class="btn btn-secondary btn-sm" onclick="printOfficialForm('rsbsa')"><i class="bi bi-printer-fill"></i> Print Form</button>
   <button type="button" class="btn btn-secondary btn-sm" onclick="downloadOfficialPdf('rsbsa','RSBSA_Enrollment_<?= htmlspecialchars(str_replace(' ','_',$fFullName)) ?>_<?= date('Ymd') ?>.pdf')"><i class="bi bi-file-earmark-pdf-fill"></i> Download PDF</button>
          </div>
        </div>

        <?php if($latestRsbsa): ?>
        <div class="form-status-banner banner-<?= $rsbsaStatus ?> no-print">
          <div>
            <b>Submission Status: <?= ucfirst($latestRsbsa['status']) ?></b> &bull; Reference No: <b><?= htmlspecialchars($latestRsbsa['reference_no'] ?? 'N/A') ?></b>
            <div style="font-size:.8rem;opacity:.9;margin-top:2px;">Submitted on <?= date('M d, Y h:i A', strtotime($latestRsbsa['created_at'])) ?>
            <?php if(!empty($latestRsbsa['remarks'])): ?>
              &bull; <i>Remarks: <?= htmlspecialchars($latestRsbsa['remarks']) ?></i>
            <?php endif; ?>
            </div>
          </div>
          <span class="sub-status-badge badge-<?= $rsbsaStatus ?>"><?= ucfirst($rsbsaStatus) ?></span>
        </div>
        <?php endif; ?>

        <div class="section-hdr">
          <div class="section-seal"><i class="bi bi-file-earmark-text-fill" style="color:var(--acc);font-size:2rem;"></i></div>
          <div>
            <h2>RSBSA Enrollment Form</h2>
            <p>Department of Agriculture &bull; Revised Form No. C1-2020<br>Registry System for Basic Sectors in Agriculture</p>
          </div>
        </div>

        <div class="toggle-row">
          <button type="button" class="tog-btn active" id="btnRsbsaNew" onclick="setRsbsaType('New')"><i class="bi bi-file-earmark-plus"></i> New Enrollment</button>
          <button type="button" class="tog-btn" id="btnRsbsaUpdate" onclick="setRsbsaType('Updating')"><i class="bi bi-arrow-repeat"></i> Existing / Updating</button>
        </div>

        <form id="rsbsaEnrollmentForm" onsubmit="submitRsbsaForm(event)" autocomplete="off">
          <input type="hidden" name="action" value="save_rsbsa_form">
          <input type="hidden" id="rsbsaEnrollmentTypeVal" name="enrollment_type" value="New">

          <div class="print-area" id="rsbsaPrintArea">

            <!-- SECTION I: Personal Information -->
            <div class="form-section">
              <div class="form-section-hdr"><span class="form-section-num">I</span> Personal Information</div>
              <div class="form-section-body">
                <div class="form-group" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:.75rem 1rem;margin-bottom:1rem;">
                  <label class="form-label" style="font-size:.75rem;color:#64748b;margin-bottom:2px;">RSBSA Reference Number</label>
                  <input type="text" class="form-input" name="reference_no" value="<?= htmlspecialchars($latestRsbsa['reference_no'] ?? ('05-20-'.date('y').'-'.str_pad($userId ?? 1, 5, '0', STR_PAD_LEFT))) ?>" readonly style="background:#ffffff;font-weight:700;font-size:1rem;color:var(--pri);letter-spacing:1px;">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">Surname *</label><input type="text" class="form-input" name="surname" value="<?= htmlspecialchars($lName) ?>" placeholder="Last name" required></div>
                  <div class="form-group"><label class="form-label">First Name *</label><input type="text" class="form-input" name="first_name" value="<?= htmlspecialchars($fName) ?>" placeholder="First name" required></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">Middle Name</label><input type="text" class="form-input" name="middle_name" value="<?= htmlspecialchars($mName) ?>" placeholder="Middle name"></div>
                  <div class="form-group"><label class="form-label">Extension Name</label><input type="text" class="form-input" name="ext_name" placeholder="Jr., Sr., III..."></div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">Sex *</label>
                    <div class="biz-row" id="rsbsaSexRow">
                      <button type="button" class="biz-pill <?= strtolower($fGender)==='male'||empty($fGender)?'active':'' ?>" onclick="toggleRsbsaSex(this,'Male')">Male</button>
                      <button type="button" class="biz-pill <?= strtolower($fGender)==='female'?'active':'' ?>" onclick="toggleRsbsaSex(this,'Female')">Female</button>
                    </div>
                    <input type="hidden" id="rsbsaSexVal" name="pet_sex" value="<?= strtolower($fGender)==='female'?'Female':'Male' ?>">
                  </div>
                  <div class="form-group"><label class="form-label">Civil Status *</label>
                    <select class="form-select" name="pet_civil_status" required>
                      <option value="Single" <?= strtolower($fCivilStatus)==='single'?'selected':'' ?>>Single</option>
                      <option value="Married" <?= strtolower($fCivilStatus)==='married'?'selected':'' ?>>Married</option>
                      <option value="Widowed" <?= strtolower($fCivilStatus)==='widowed'||strtolower($fCivilStatus)==='widow'?'selected':'' ?>>Widowed</option>
                      <option value="Separated" <?= strtolower($fCivilStatus)==='separated'?'selected':'' ?>>Separated</option>
                    </select>
                  </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">Spouse's Name (if married)</label><input type="text" class="form-input" name="pet_spouse" placeholder="Full name of spouse"></div>
                  <div class="form-group"><label class="form-label">Mother's Maiden Name</label><input type="text" class="form-input" name="pet_mothers_name" value="<?= htmlspecialchars($primaryFarm['pet_mothers_name'] ?? '') ?>" placeholder="Full maiden name"></div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">Date of Birth *</label><input type="date" class="form-input" name="pet_dob" value="<?= htmlspecialchars($fDob) ?>" required></div>
                  <div class="form-group"><label class="form-label">Place of Birth</label><input type="text" class="form-input" name="pet_pob" value="<?= htmlspecialchars($primaryFarm['pet_pob'] ?? 'Camarines Sur') ?>" placeholder="City/Municipality"></div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">Contact Number *</label><input type="tel" class="form-input" name="contact_phone" value="<?= htmlspecialchars($fMobile) ?>" placeholder="+63 9XX XXX XXXX" required></div>
                  <div class="form-group"><label class="form-label">Household Head?</label>
                    <div class="biz-row" id="rsbsaHhHeadRow">
                      <button type="button" class="biz-pill active" onclick="toggleRsbsaHhHead(this,'Yes')">Yes</button>
                      <button type="button" class="biz-pill" onclick="toggleRsbsaHhHead(this,'No')">No</button>
                    </div>
                    <input type="hidden" id="rsbsaHhHeadVal" name="hh_head" value="Yes">
                    <input type="text" class="form-input" id="rsbsaHhHeadName" name="hh_head_name" placeholder="If no, specify household head name & relationship" style="display:none;margin-top:.4rem;">
                  </div>
                </div>

                <div style="margin-top:.5rem;padding-top:.75rem;border-top:1px solid #f1f5f9;">
                  <strong style="font-size:.84rem;color:#0f172a;display:block;margin-bottom:.5rem;">Permanent Home Address</strong>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div class="form-group"><label class="form-label">House/Lot/Bldg No.</label><input type="text" class="form-input" name="pet_house_no" value="<?= htmlspecialchars($primaryFarm['pet_house_no'] ?? '') ?>" placeholder="House/Lot/Bldg No."></div>
                    <div class="form-group"><label class="form-label">Street / Sitio / Subdv.</label><input type="text" class="form-input" name="pet_street" value="<?= htmlspecialchars($primaryFarm['pet_street'] ?? '') ?>" placeholder="Street / Sitio / Subdv."></div>
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div class="form-group"><label class="form-label">Barangay *</label><input type="text" class="form-input" name="pet_barangay" value="<?= htmlspecialchars($fBarangay ?: ($primaryFarm['pet_barangay'] ?? '')) ?>" placeholder="Barangay" required></div>
                    <div class="form-group"><label class="form-label">Municipality / City *</label><input type="text" class="form-input" name="pet_municipality" value="<?= htmlspecialchars($fMunicipality ?: ($primaryFarm['pet_municipality'] ?? 'Camarines Sur')) ?>" placeholder="Municipality/City" required></div>
                  </div>
                  <div class="form-group"><label class="form-label">Province</label><input type="text" class="form-input" name="pet_province" value="<?= htmlspecialchars($fProvince ?: 'Camarines Sur') ?>" readonly></div>
                </div>

                <div style="margin-top:.5rem;padding-top:.75rem;border-top:1px solid #f1f5f9;">
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div class="form-group"><label class="form-label">Highest Formal Education</label>
                      <select class="form-select" name="pet_education">
                        <option value="Elementary">Elementary</option>
                        <option value="High School" selected>High School</option>
                        <option value="Vocational">Vocational</option>
                        <option value="College">College</option>
                        <option value="Post-Graduate">Post-Graduate</option>
                        <option value="None">None</option>
                      </select>
                    </div>
                    <div class="form-group"><label class="form-label">Government ID Number</label>
                      <input type="text" class="form-input" name="gov_id_number" value="<?= htmlspecialchars($fGovId) ?>" placeholder="PhilSys / Voter's / SSS #">
                    </div>
                  </div>

                  <div class="form-group"><label class="form-label">Special Sectors (Tick all that apply)</label>
                    <div class="cb-grid">
                      <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="pwd" value="Yes"> Person with Disability (PWD)</label>
                      <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="fourps" value="Yes"> 4Ps Beneficiary</label>
                      <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="indigenous" value="Yes"> Indigenous People (IP)</label>
                    </div>
                    <input type="text" class="form-input" name="indigenous_specify" placeholder="If IP, specify tribe..." style="margin-top:.4rem;">
                  </div>

                  <div class="form-group"><label class="form-label">Member of Farmers Association / Cooperative?</label>
                    <div class="biz-row" id="rsbsaAssocRow">
                      <button type="button" class="biz-pill active" onclick="toggleRsbsaAssoc(this,'No')">No</button>
                      <button type="button" class="biz-pill" onclick="toggleRsbsaAssoc(this,'Yes')">Yes</button>
                    </div>
                    <input type="hidden" id="rsbsaAssocVal" name="farmers_assoc" value="No">
                    <input type="text" class="form-input" id="rsbsaAssocName" name="farmers_assoc_name" placeholder="If yes, specify name of association or cooperative..." style="display:none;margin-top:.4rem;">
                  </div>

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div class="form-group"><label class="form-label">Emergency Contact Person</label><input type="text" class="form-input" name="emergency_contact_name" value="<?= htmlspecialchars($fEmergencyName) ?>" placeholder="Full name"></div>
                    <div class="form-group"><label class="form-label">Emergency Contact Number</label><input type="tel" class="form-input" name="emergency_contact_no" value="<?= htmlspecialchars($fEmergencyPhone) ?>" placeholder="+63 9XX XXX XXXX"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- SECTION II: Farm Profile & Livelihood Commodities -->
            <div class="form-section">
              <div class="form-section-hdr"><span class="form-section-num">II</span> Farm Profile &amp; Livelihood Commodities</div>
              <div class="form-section-body">
                <label class="form-label" style="font-weight:700;margin-bottom:.5rem;">Main Livelihood Category &amp; Commodities</label>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;">
                  <!-- Category 1: Farmer -->
                  <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:12px;padding:.875rem;">
                    <label style="display:flex;align-items:center;gap:.5rem;font-weight:700;color:var(--pri);font-size:.88rem;cursor:pointer;">
                      <input type="checkbox" name="farming_farmer" value="1" checked style="accent-color:var(--acc);width:16px;height:16px;">
                      2.1 Farmer (Crop / Livestock Producer)
                    </label>
                    <div style="margin-top:.6rem;display:flex;flex-direction:column;gap:.4rem;font-size:.82rem;padding-left:1.5rem;">
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="farming_rice" value="1"> Rice</label>
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="farming_corn" value="1"> Corn</label>
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="farming_livestock" value="1" checked> Livestock</label>
                      <input type="text" class="form-input" name="livestock_specify" value="Cattle, Swine, Goat, Carabao" placeholder="Specify livestock...">
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="farming_poultry" value="1"> Poultry</label>
                      <input type="text" class="form-input" name="poultry_specify" placeholder="Specify poultry (Broiler, Native)...">
                    </div>
                  </div>

                  <!-- Category 2: Farmworker -->
                  <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:12px;padding:.875rem;">
                    <label style="display:flex;align-items:center;gap:.5rem;font-weight:700;color:var(--pri);font-size:.88rem;cursor:pointer;">
                      <input type="checkbox" name="farming_worker" value="1" style="accent-color:var(--acc);width:16px;height:16px;">
                      2.2 Farmworker / Laborer
                    </label>
                    <div style="margin-top:.6rem;display:flex;flex-direction:column;gap:.4rem;font-size:.82rem;padding-left:1.5rem;">
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="work_landprep" value="1"> Land Preparation</label>
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="work_planting" value="1"> Planting / Transplanting</label>
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="work_cultivation" value="1"> Cultivation / Weeding</label>
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="work_harvest" value="1"> Harvesting / Threshing</label>
                    </div>
                  </div>

                  <!-- Category 3: Fisherfolk -->
                  <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:12px;padding:.875rem;">
                    <label style="display:flex;align-items:center;gap:.5rem;font-weight:700;color:var(--pri);font-size:.88rem;cursor:pointer;">
                      <input type="checkbox" name="farming_fisher" value="1" style="accent-color:var(--acc);width:16px;height:16px;">
                      2.3 Fisherfolk
                    </label>
                    <div style="margin-top:.6rem;display:flex;flex-direction:column;gap:.4rem;font-size:.82rem;padding-left:1.5rem;">
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="fish_capture" value="1"> Fish Capture</label>
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="fish_culture" value="1"> Aquaculture</label>
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="fish_glean" value="1"> Gleaning</label>
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="fish_process" value="1"> Processing / Vending</label>
                    </div>
                  </div>

                  <!-- Category 4: Agri Youth -->
                  <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:12px;padding:.875rem;">
                    <label style="display:flex;align-items:center;gap:.5rem;font-weight:700;color:var(--pri);font-size:.88rem;cursor:pointer;">
                      <input type="checkbox" name="farming_youth" value="1" style="accent-color:var(--acc);width:16px;height:16px;">
                      2.4 Agri Youth
                    </label>
                    <div style="margin-top:.6rem;display:flex;flex-direction:column;gap:.4rem;font-size:.82rem;padding-left:1.5rem;">
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="youth_hh" value="1"> Part of Farming Household</label>
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="youth_course" value="1"> Enrolled in Agri Course / Scholar</label>
                      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="checkbox" name="youth_program" value="1"> Participating in Agri Youth Program</label>
                    </div>
                  </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">Gross Annual Farming Income (PHP)</label><input type="number" class="form-input" name="income_farming" value="75000" placeholder="0.00" min="0" step="100"></div>
                  <div class="form-group"><label class="form-label">Gross Annual Non-Farming Income (PHP)</label><input type="number" class="form-input" name="income_non_farming" value="25000" placeholder="0.00" min="0" step="100"></div>
                </div>

                <div class="form-group"><label class="form-label">Total Number of Farm Parcels</label><input type="number" class="form-input" name="parcel_count" value="1" min="1" max="5"></div>

                <div style="margin-top:1rem;">
                  <label class="form-label" style="font-weight:700;margin-bottom:.5rem;">2.8 Farm Parcel Details</label>
                  <div class="table-responsive" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                    <table class="table" style="width:100%;margin:0;font-size:.84rem;">
                      <thead style="background:#f8fafc;border-bottom:1.5px solid #e2e8f0;">
                        <tr>
                          <th style="padding:.75rem;width:12%;">Parcel #</th>
                          <th style="padding:.75rem;width:30%;">Location (Barangay, Municipality)</th>
                          <th style="padding:.75rem;width:16%;">Area (ha)</th>
                          <th style="padding:.75rem;width:22%;">Ownership Type</th>
                          <th style="padding:.75rem;width:20%;">Commodities / Animals</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="padding:.6rem .75rem;font-weight:700;color:var(--pri);">Parcel 1</td>
                          <td style="padding:.6rem .75rem;"><input type="text" class="form-input" name="parcel_loc_1" value="<?= htmlspecialchars(($fBarangay ?: 'Poblacion').', '.($fMunicipality ?: 'Camarines Sur')) ?>"></td>
                          <td style="padding:.6rem .75rem;"><input type="number" step="0.01" class="form-input" name="parcel_area_1" value="1.50" style="text-align:center;"></td>
                          <td style="padding:.6rem .75rem;">
                            <select class="form-select" name="parcel_owner_1">
                              <option value="Registered Owner" selected>Registered Owner</option>
                              <option value="Tenant">Tenant / Sharecropper</option>
                              <option value="Lessee">Lessee / Renter</option>
                              <option value="Others">Others</option>
                            </select>
                          </td>
                          <td style="padding:.6rem .75rem;"><input type="text" class="form-input" name="parcel_crops_1" value="Cattle, Swine, Forage Grass"></td>
                        </tr>
                        <tr>
                          <td style="padding:.6rem .75rem;font-weight:700;color:var(--pri);">Parcel 2</td>
                          <td style="padding:.6rem .75rem;"><input type="text" class="form-input" name="parcel_loc_2" placeholder="Optional 2nd Parcel location"></td>
                          <td style="padding:.6rem .75rem;"><input type="number" step="0.01" class="form-input" name="parcel_area_2" placeholder="0.00" style="text-align:center;"></td>
                          <td style="padding:.6rem .75rem;">
                            <select class="form-select" name="parcel_owner_2">
                              <option value="">-- None --</option>
                              <option value="Registered Owner">Registered Owner</option>
                              <option value="Tenant">Tenant</option>
                              <option value="Lessee">Lessee</option>
                            </select>
                          </td>
                          <td style="padding:.6rem .75rem;"><input type="text" class="form-input" name="parcel_crops_2" placeholder="Commodity"></td>
                        </tr>
                        <tr>
                          <td style="padding:.6rem .75rem;font-weight:700;color:var(--pri);">Parcel 3</td>
                          <td style="padding:.6rem .75rem;"><input type="text" class="form-input" name="parcel_loc_3" placeholder="Optional 3rd Parcel location"></td>
                          <td style="padding:.6rem .75rem;"><input type="number" step="0.01" class="form-input" name="parcel_area_3" placeholder="0.00" style="text-align:center;"></td>
                          <td style="padding:.6rem .75rem;">
                            <select class="form-select" name="parcel_owner_3">
                              <option value="">-- None --</option>
                              <option value="Registered Owner">Registered Owner</option>
                              <option value="Tenant">Tenant</option>
                              <option value="Lessee">Lessee</option>
                            </select>
                          </td>
                          <td style="padding:.6rem .75rem;"><input type="text" class="form-input" name="parcel_crops_3" placeholder="Commodity"></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- SECTION III: Declaration & Data Privacy Consent -->
            <div class="form-section">
              <div class="form-section-hdr"><span class="form-section-num">III</span> Declaration &amp; Consent</div>
              <div class="form-section-body">
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:1rem;margin-bottom:1rem;color:#166534;font-size:.84rem;line-height:1.5;">
                  <i class="bi bi-shield-check" style="font-size:1.15rem;vertical-align:-2px;color:#15803d;"></i>
                  <strong>Farmer's Declaration:</strong> I hereby declare that all information indicated in this application is true, correct, and complete to the best of my knowledge. I understand that any false statement or material omission shall be ground for disqualification and forfeiture of government agricultural assistance under Republic of the Philippines laws.
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1rem;">
                  <div>
                    <label class="form-label" style="font-size:.75rem;color:#64748b;margin-bottom:2px;">Applicant Electronic Signature</label>
                    <div style="font-size:1.1rem;font-weight:800;color:var(--pri);border-bottom:2px solid var(--acc);padding-bottom:4px;display:inline-block;"><?= htmlspecialchars($fFullName) ?></div>
                    <div style="font-size:.75rem;color:#64748b;margin-top:4px;">Registered RSBSA Applicant</div>
                  </div>
                  <div>
                    <label class="form-label" style="font-size:.75rem;color:#64748b;margin-bottom:2px;">Date of Filing</label>
                    <div style="font-size:1rem;font-weight:700;color:#0f172a;"><?= date('F d, Y') ?></div>
                    <div style="font-size:.75rem;color:#64748b;margin-top:4px;">Municipal Agriculture Office &bull; Camarines Sur</div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <button type="submit" class="btn-submit" id="btnSubmitRsbsa" style="margin-top:.75rem;">
            <i class="bi bi-send-fill"></i> Submit RSBSA Enrollment Form
          </button>
        </form>
      </div>

      <!-- ===== PCIC LIVESTOCK INSURANCE FORM ===== -->
      <div id="pg-pcic-form" class="page">
        <div class="page-top-actions no-print" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;background:#fff;padding:.875rem 1.25rem;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.05);">
          <div style="display:flex;align-items:center;gap:.75rem;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="navTo('dashboard')"><i class="bi bi-arrow-left"></i> Dashboard</button>
            <h3 style="margin:0;font-size:1rem;font-weight:700;color:var(--pri);">PCIC Livestock Mortality Insurance</h3>
            <?php if($pcicStatus): ?>
              <span class="sub-status-badge badge-<?= $pcicStatus ?>" style="font-size:.75rem;padding:.2rem .6rem;"><?= ucfirst($pcicStatus) ?></span>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:.5rem;">
               <button type="button" class="btn btn-secondary btn-sm" onclick="printOfficialForm('pcic')"><i class="bi bi-printer-fill"></i> Print Form</button>
   <button type="button" class="btn btn-secondary btn-sm" onclick="downloadOfficialPdf('pcic','PCIC_LIV-UPI-1_<?= htmlspecialchars(str_replace(' ','_',$fFullName)) ?>_<?= date('Ymd') ?>.pdf')"><i class="bi bi-file-earmark-pdf-fill"></i> Download PDF</button>
          </div>
        </div>

        <?php if($latestPcic): ?>
        <div class="form-status-banner banner-<?= $pcicStatus ?> no-print">
          <div>
            <b>Application Status: <?= ucfirst($latestPcic['status']) ?></b> &bull; App #: <b><?= htmlspecialchars($latestPcic['application_no'] ?? 'N/A') ?></b>
            <div style="font-size:.8rem;opacity:.9;margin-top:2px;">Submitted on <?= date('M d, Y h:i A', strtotime($latestPcic['created_at'])) ?>
            <?php if(!empty($latestPcic['remarks'])): ?>
              &bull; <i>Remarks: <?= htmlspecialchars($latestPcic['remarks']) ?></i>
            <?php endif; ?>
            </div>
          </div>
          <span class="sub-status-badge badge-<?= $pcicStatus ?>"><?= ucfirst($pcicStatus) ?></span>
        </div>
        <?php endif; ?>

        <div class="section-hdr">
          <div class="section-seal"><i class="bi bi-shield-check" style="color:var(--acc);font-size:2rem;"></i></div>
          <div>
            <h2>PCIC Livestock Mortality Insurance</h2>
            <p>Philippine Crop Insurance Corporation &bull; Form LIV-UPI-1<br>Official Agricultural Insurance Application &amp; Mortality Coverage</p>
          </div>
        </div>

        <div class="toggle-row">
          <button type="button" class="tog-btn active" id="btnPcicNew" onclick="setPcicAppType('New')"><i class="bi bi-file-earmark-plus"></i> New Application</button>
          <button type="button" class="tog-btn" id="btnPcicRenew" onclick="setPcicAppType('Renewal')"><i class="bi bi-arrow-repeat"></i> Renewal</button>
          <button type="button" class="tog-btn active" id="btnPcicSelf" onclick="setPcicCategory('Self-Financed')"><i class="bi bi-person-check"></i> Self-Financed</button>
          <button type="button" class="tog-btn" id="btnPcicBorrow" onclick="setPcicCategory('Borrowing')"><i class="bi bi-bank"></i> Borrowing</button>
        </div>

        <form id="pcicApplicationForm" onsubmit="submitPcicForm(event)" autocomplete="off">
          <input type="hidden" name="action" value="save_pcic_form">
          <input type="hidden" id="pcicAppTypeVal" name="application_type" value="New">
          <input type="hidden" id="pcicFarmerCatVal" name="farmer_category" value="Self-Financed">
          <input type="hidden" name="pcic_reg_office" value="V (Bicol Region)">

          <!-- LENDER / MFI WRAPPER (shown when Borrowing is active) -->
          <div id="pcicLenderWrap" style="display:none;margin-bottom:1rem;background:#eef2ff;border:1.5px solid #c7d2fe;border-radius:14px;padding:1rem;">
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem;">
              <i class="bi bi-bank" style="font-size:1.15rem;color:#4f46e5;"></i>
              <strong style="font-size:.88rem;color:#3730a3;">Lending Institution / MFI Details</strong>
            </div>
            <div class="form-group">
              <label class="form-label" style="color:#3730a3;">Lender / Financial Institution Name *</label>
              <input type="text" class="form-input" name="lender_name" placeholder="Specify LandBank, DBP, rural bank, or cooperative lender..." style="background:#fff;border-color:#a5b4fc;">
            </div>
          </div>

          <div class="print-area" id="pcicPrintArea">

            <!-- SECTION A: Basic Applicant Information -->
            <div class="form-section">
              <div class="form-section-hdr"><span class="form-section-num">A</span> Basic Applicant Information</div>
              <div class="form-section-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">Last Name *</label><input type="text" class="form-input" name="last_name" value="<?= htmlspecialchars($lName) ?>" placeholder="Last name" required></div>
                  <div class="form-group"><label class="form-label">First Name *</label><input type="text" class="form-input" name="first_name" value="<?= htmlspecialchars($fName) ?>" placeholder="First name" required></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">Middle Name</label><input type="text" class="form-input" name="middle_name" value="<?= htmlspecialchars($mName) ?>" placeholder="Middle name"></div>
                  <div class="form-group"><label class="form-label">Suffix</label><input type="text" class="form-input" name="suffix" placeholder="Jr., Sr., III..."></div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">Contact Number (11 digits) *</label><input type="tel" class="form-input" name="contact_no" value="<?= htmlspecialchars($fMobile) ?>" placeholder="09XXXXXXXXX" required></div>
                  <div class="form-group"><label class="form-label">Date of Birth *</label><input type="date" class="form-input" name="dob" value="<?= htmlspecialchars($fDob) ?>" required></div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">Sex *</label>
                    <div class="biz-row" id="pcicSexRow">
                      <button type="button" class="biz-pill <?= strtolower($fGender)==='male'||empty($fGender)?'active':'' ?>" onclick="togglePcicSex(this,'Male')">Male</button>
                      <button type="button" class="biz-pill <?= strtolower($fGender)==='female'?'active':'' ?>" onclick="togglePcicSex(this,'Female')">Female</button>
                    </div>
                    <input type="hidden" id="pcicSexVal" name="sex" value="<?= strtolower($fGender)==='female'?'Female':'Male' ?>">
                  </div>
                  <div class="form-group"><label class="form-label">Civil Status *</label>
                    <div class="biz-row" id="pcicCivilRow">
                      <button type="button" class="biz-pill <?= strtolower($fCivilStatus)==='single'||empty($fCivilStatus)?'active':'' ?>" onclick="togglePcicCivil(this,'Single')">Single</button>
                      <button type="button" class="biz-pill <?= strtolower($fCivilStatus)==='married'?'active':'' ?>" onclick="togglePcicCivil(this,'Married')">Married</button>
                      <button type="button" class="biz-pill <?= strtolower($fCivilStatus)==='widowed'||strtolower($fCivilStatus)==='widow'?'active':'' ?>" onclick="togglePcicCivil(this,'Widow')">Widow</button>
                      <button type="button" class="biz-pill <?= strtolower($fCivilStatus)==='separated'?'active':'' ?>" onclick="togglePcicCivil(this,'Separated')">Separated</button>
                    </div>
                    <input type="hidden" id="pcicCivilVal" name="civil_status" value="<?= ucfirst($fCivilStatus ?: 'Single') ?>">
                  </div>
                </div>

                <div class="form-group"><label class="form-label">Complete Address *</label>
                  <input type="text" class="form-input" name="applicant_address" value="<?= htmlspecialchars($fAddress ?: ($fBarangay.', '.$fMunicipality.', '.$fProvince)) ?>" placeholder="House/Lot No., Street, Barangay, Municipality, Province" required>
                </div>

                <div class="form-group"><label class="form-label">Special Sector (Tick all that apply)</label>
                  <div class="cb-grid">
                    <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="sector_pwd" value="1"> Person with Disability (PWD)</label>
                    <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="sector_sc" value="1"> Senior Citizen</label>
                    <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="sector_youth" value="1"> Youth (18-30)</label>
                    <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="sector_ip" value="1"> Indigenous People</label>
                  </div>
                </div>

                <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid #f1f5f9;">
                  <strong style="font-size:.84rem;color:#0f172a;display:block;margin-bottom:.5rem;">A.8 Legal Beneficiary (In case of applicant mortality benefit)</strong>
                  <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
                    <div class="form-group"><label class="form-label">Primary Beneficiary Name</label><input type="text" class="form-input" name="bene_primary_name" placeholder="Full name of beneficiary"></div>
                    <div class="form-group"><label class="form-label">Relationship</label><input type="text" class="form-input" name="bene_primary_rel" placeholder="e.g. Spouse / Child"></div>
                    <div class="form-group"><label class="form-label">Age</label><input type="number" class="form-input" name="bene_primary_age" placeholder="Age" min="0"></div>
                  </div>
                  <div style="display:grid;grid-template-columns:2fr 1fr;gap:.75rem;">
                    <div class="form-group"><label class="form-label">Contingent Beneficiary / Guardian</label><input type="text" class="form-input" name="bene_contingent_name" placeholder="Full name of contingent beneficiary"></div>
                    <div class="form-group"><label class="form-label">Relationship</label><input type="text" class="form-input" name="bene_contingent_rel" placeholder="Relationship"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- SECTION B: Farm and Livestock Information -->
            <div class="form-section">
              <div class="form-section-hdr"><span class="form-section-num">B</span> Farm and Livestock Information</div>
              <div class="form-section-body">
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">B.1 Farm Address / Location *</label><input type="text" class="form-input" name="farm_location" value="<?= htmlspecialchars($primaryFarm['address'] ?? ($fBarangay.', '.$fMunicipality.', '.$fProvince)) ?>" placeholder="Barangay, Municipality, Province" required></div>
                  <div class="form-group"><label class="form-label">Facility Type *</label>
                    <div class="biz-row" id="pcicFacilityRow">
                      <button type="button" class="biz-pill active" onclick="togglePcicFacility(this,'Backyard')">Backyard</button>
                      <button type="button" class="biz-pill" onclick="togglePcicFacility(this,'Commercial')">Commercial</button>
                    </div>
                    <input type="hidden" id="pcicFacilityVal" name="facility_type" value="Backyard">
                  </div>
                </div>

                <div class="form-group"><label class="form-label">B.2 Primary Animal Type to Insure *</label>
                  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.5rem;margin-top:.25rem;">
                    <label class="cb-item checked" style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="radio" name="animal_type" value="Cattle" checked style="accent-color:var(--acc);"> <strong>Cattle</strong></label>
                    <label class="cb-item" style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="radio" name="animal_type" value="Carabao" style="accent-color:var(--acc);"> <strong>Carabao</strong></label>
                    <label class="cb-item" style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="radio" name="animal_type" value="Swine" style="accent-color:var(--acc);"> <strong>Swine</strong></label>
                    <label class="cb-item" style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="radio" name="animal_type" value="Goat/Sheep" style="accent-color:var(--acc);"> <strong>Goat / Sheep</strong></label>
                    <label class="cb-item" style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="radio" name="animal_type" value="Poultry" style="accent-color:var(--acc);"> <strong>Poultry</strong></label>
                    <label class="cb-item" style="display:flex;align-items:center;gap:.5rem;cursor:pointer;"><input type="radio" name="animal_type" value="Horse/Others" style="accent-color:var(--acc);"> <strong>Horse / Others</strong></label>
                  </div>
                </div>

                <div style="margin-top:1rem;">
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;">
                    <label class="form-label" style="font-weight:700;margin:0;">B.3 Description of Animals to be Insured</label>
                    <span style="font-size:.72rem;color:#64748b;">(Up to 6 entries &bull; attach extra sheets if needed)</span>
                  </div>

                  <div class="table-responsive" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                    <table class="table" style="width:100%;margin:0;font-size:.82rem;">
                      <thead style="background:#f8fafc;border-bottom:1.5px solid #e2e8f0;">
                        <tr>
                          <th style="padding:.65rem;width:18%;">Tag / ID No.</th>
                          <th style="padding:.65rem;width:8%;">Count M</th>
                          <th style="padding:.65rem;width:8%;">Count F</th>
                          <th style="padding:.65rem;width:14%;">Age / Group</th>
                          <th style="padding:.65rem;width:14%;">Date of Birth</th>
                          <th style="padding:.65rem;width:14%;">Breed</th>
                          <th style="padding:.65rem;width:10%;">Color</th>
                          <th style="padding:.65rem;width:14%;">Value (PhP)</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php for($i=1; $i<=6; $i++): 
                          $tagDef = ($i===1 && !empty($allLvRows[0]['tagId'])) ? $allLvRows[0]['tagId'] : '';
                          $breedDef = ($i===1 && !empty($allLvRows[0]['breed'])) ? $allLvRows[0]['breed'] : '';
                        ?>
                        <tr>
                          <td style="padding:.45rem .65rem;"><input type="text" class="form-input" name="animal_tag_<?= $i ?>" value="<?= htmlspecialchars($tagDef) ?>" placeholder="Tag #"></td>
                          <td style="padding:.45rem .65rem;"><input type="number" class="form-input" name="animal_m_<?= $i ?>" min="0" placeholder="0" style="text-align:center;"></td>
                          <td style="padding:.45rem .65rem;"><input type="number" class="form-input" name="animal_f_<?= $i ?>" min="0" placeholder="0" style="text-align:center;"></td>
                          <td style="padding:.45rem .65rem;"><input type="text" class="form-input" name="animal_age_<?= $i ?>" placeholder="e.g. 2 yrs"></td>
                          <td style="padding:.45rem .65rem;"><input type="date" class="form-input" name="animal_dob_<?= $i ?>"></td>
                          <td style="padding:.45rem .65rem;"><input type="text" class="form-input" name="animal_breed_<?= $i ?>" value="<?= htmlspecialchars($breedDef) ?>" placeholder="Breed"></td>
                          <td style="padding:.45rem .65rem;"><input type="text" class="form-input" name="animal_color_<?= $i ?>" placeholder="Color"></td>
                          <td style="padding:.45rem .65rem;"><input type="number" step="0.01" class="form-input" name="animal_val_<?= $i ?>" placeholder="0.00" style="text-align:right;"></td>
                        </tr>
                        <?php endfor; ?>
                      </tbody>
                    </table>
                  </div>
                  <div style="font-size:.72rem;color:#64748b;margin-top:.4rem;">* Note: For poultry, indicate building/coop unit; for large livestock, enter individual animal ear tags.</div>
                </div>

                <div style="margin-top:1rem;display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">B.4 Total Heads to Insure *</label><input type="number" class="form-input" name="head_count" value="1" min="1" required style="font-weight:700;"></div>
                  <div class="form-group"><label class="form-label">B.5 Date Purchased</label><input type="date" class="form-input" name="purchase_date" value="<?= date('Y-m-d') ?>"></div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;">
                  <div class="form-group"><label class="form-label">B.6 Stock Source</label>
                    <div class="biz-row" id="pcicSourceRow">
                      <button type="button" class="biz-pill active" onclick="togglePcicSource(this,'Island-Born')">Island-Born</button>
                      <button type="button" class="biz-pill" onclick="togglePcicSource(this,'Imported')">Imported</button>
                    </div>
                    <input type="hidden" id="pcicSourceVal" name="stock_source" value="Island-Born">
                  </div>
                  <div class="form-group"><label class="form-label">B.7 Housing Units</label><input type="number" class="form-input" name="housing_units" value="1" min="1"></div>
                  <div class="form-group"><label class="form-label">B.8 Heads per Unit</label><input type="number" class="form-input" name="heads_per_unit" value="1" min="1"></div>
                </div>
              </div>
            </div>

            <!-- SECTION C: Certification and Data Privacy Consent Statement -->
            <div class="form-section">
              <div class="form-section-hdr"><span class="form-section-num">C</span> Certification &amp; Consent</div>
              <div class="form-section-body">
                <div style="display:flex;flex-direction:column;gap:.6rem;margin-bottom:1rem;">
                  <label class="cb-item checked" style="display:flex;align-items:flex-start;gap:.65rem;cursor:pointer;padding:.75rem;">
                    <input type="checkbox" name="cert_agree_1" value="1" checked required style="accent-color:var(--acc);margin-top:3px;flex-shrink:0;">
                    <span style="font-size:.82rem;line-height:1.4;"><b>1. Truth of Statements:</b> I certify that all statements made in this application are true, complete and correct to the best of my knowledge. Any misrepresentation shall void coverage.</span>
                  </label>
                  <label class="cb-item checked" style="display:flex;align-items:flex-start;gap:.65rem;cursor:pointer;padding:.75rem;">
                    <input type="checkbox" name="cert_agree_2" value="1" checked required style="accent-color:var(--acc);margin-top:3px;flex-shrink:0;">
                    <span style="font-size:.82rem;line-height:1.4;"><b>2. Data Privacy Act of 2012 Consent:</b> I consent to the collection, processing and sharing of my data under Republic Act No. 10173 for crop and livestock insurance administration.</span>
                  </label>
                  <label class="cb-item checked" style="display:flex;align-items:flex-start;gap:.65rem;cursor:pointer;padding:.75rem;">
                    <input type="checkbox" name="cert_agree_3" value="1" checked style="accent-color:var(--acc);margin-top:3px;flex-shrink:0;">
                    <span style="font-size:.82rem;line-height:1.4;"><b>3. Deed of Assignment:</b> If borrowing or covered under government subsidy, I hereby assign policy indemnity rights to the lending institution or designated beneficiary program.</span>
                  </label>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1rem;">
                  <div>
                    <label class="form-label" style="font-size:.75rem;color:#64748b;margin-bottom:2px;">Applicant Electronic Signature</label>
                    <div style="font-size:1.1rem;font-weight:800;color:var(--pri);border-bottom:2px solid var(--acc);padding-bottom:4px;display:inline-block;"><?= htmlspecialchars($fFullName) ?></div>
                    <div style="font-size:.75rem;color:#64748b;margin-top:4px;">Signature over Printed Name</div>
                  </div>
                  <div>
                    <label class="form-label" style="font-size:.75rem;color:#64748b;margin-bottom:2px;">Date Signed</label>
                    <div style="font-size:1rem;font-weight:700;color:#0f172a;"><?= date('F d, Y') ?></div>
                    <div style="font-size:.75rem;color:#64748b;margin-top:4px;">PCIC Regional Office No. V (Bicol Region)</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- SECTION D: Coverage Details (For PCIC / DA Use Only) -->
            <div class="form-section">
              <div class="form-section-hdr" style="background:linear-gradient(90deg,#eff6ff,#f8fafc);color:#1e40af;"><span class="form-section-num" style="background:#3b82f6;">D</span> Coverage Information (DA / PCIC Subsidized Package)</div>
              <div class="form-section-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
                  <div class="form-group"><label class="form-label">D.1 Estimated Sum Insured / Head</label><input type="text" class="form-input" name="pcic_d1_sum_head" value="Subject to DA / PCIC Evaluation" readonly style="background:#f8fafc;color:#64748b;"></div>
                  <div class="form-group"><label class="form-label">D.2 Premium Source</label><input type="text" class="form-input" value="100% Subsidized under RSBSA (Free for Qualified Farmers)" readonly style="background:#ecfdf5;color:#065f46;font-weight:700;"></div>
                </div>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.75rem .875rem;font-size:.82rem;color:#475569;">
                  <i class="bi bi-shield-check" style="color:#10b981;"></i> <b>D.3 Extended Epidemic Coverage:</b> Automatically includes coverage for Anthrax, Foot-and-Mouth Disease (FMD), African Swine Fever (ASF), Avian Influenza (Bird Flu), and Hemorrhagic Septicemia under standard subsidized guidelines.
                </div>
              </div>
            </div>

          </div>

          <button type="submit" class="btn-submit" id="btnSubmitPcic" style="margin-top:.75rem;">
            <i class="bi bi-shield-fill-check"></i> Submit PCIC Insurance Application
          </button>
        </form>
      </div>

      <!-- ===== LIVESTOCK ===== -->
      <div id="pg-livestock" class="page">
        <?php
        $flatLivestock = [];
        $totalHeadCount = 0;
        $healthyCount = 0;
        $sickCount = 0;
        $vaxCompleteCount = 0;
        $speciesCounts = [];
        $healthCounts = ['Healthy' => 0, 'Sick' => 0, 'Recovered' => 0, 'Deceased' => 0];
        $vaxCounts = ['Complete' => 0, 'Partial' => 0, 'None' => 0];

        foreach($allLvRows as $r) {
            if (!empty($r['tagId'])) {
                $flatLivestock[] = $r;
                $q = (int)($r['qty'] ?? 1);
                $totalHeadCount += $q;
                $st = $r['healthStatus'] ?? 'Healthy';
                if ($st === 'Healthy') $healthyCount += $q;
                elseif ($st === 'Sick') $sickCount += $q;
                if (($r['vaccinationStatus'] ?? '') === 'Complete') $vaxCompleteCount += $q;

                $sp = trim($r['type'] ?? 'Other') ?: 'Other';
                $speciesCounts[$sp] = ($speciesCounts[$sp] ?? 0) + $q;

                $hs = trim($r['healthStatus'] ?? 'Healthy');
                if (!isset($healthCounts[$hs])) $healthCounts[$hs] = 0;
                $healthCounts[$hs] += $q;

                $vs = trim($r['vaccinationStatus'] ?? 'None');
                if (!isset($vaxCounts[$vs])) $vaxCounts[$vs] = 0;
                $vaxCounts[$vs] += $q;
            }
        }
        $healthRate = $totalHeadCount > 0 ? round(($healthyCount / $totalHeadCount) * 100) : 100;
        $vaxRate = $totalHeadCount > 0 ? round(($vaxCompleteCount / $totalHeadCount) * 100) : 0;
        ?>

        <!-- 1. Modern Web App Header -->
        <div class="lv-app-header">
          <div class="lv-header-content">
            <div>
              <div class="lv-badge-pill">
                <span class="lv-pulse-dot"></span>
                <span>Live Herd Registry &bull; <?= count($livestockByFarm) ?> <?= count($livestockByFarm) === 1 ? 'Farm' : 'Farms' ?> Connected</span>
              </div>
              <h1 style="font-size:1.6rem;font-weight:800;letter-spacing:-0.02em;margin:0 0 0.35rem;line-height:1.2;">
                Livestock Management
              </h1>
              <p style="margin:0;font-size:0.84rem;opacity:0.92;max-width:620px;line-height:1.45;">
                Track animal health records, vaccination schedules, ear tag credentials, and herd productivity across all your registered farm facilities.
              </p>
            </div>
            <div class="lv-header-actions">
              <?php if(!empty($farmsData)): ?>
              <button type="button" class="lv-btn-primary" onclick="openModal('lvAddModal')">
                <i class="bi bi-plus-circle-fill"></i> Add Animal
              </button>
              <?php endif; ?>
              <div class="lv-dropdown-wrap" id="lvExportDropdownWrap">
                <button type="button" class="lv-btn-glass" onclick="toggleLvExportMenu(event)" title="Export Livestock Reports & Spreadsheets">
                  <i class="bi bi-file-earmark-spreadsheet"></i> Export Reports <i class="bi bi-chevron-down" style="font-size:0.75rem;margin-left:3px;"></i>
                </button>
                <div class="lv-dropdown-menu" id="lvExportMenu">
                  <a href="?export=livestock&format=csv" class="lv-menu-item" title="Download CSV Spreadsheet with KPI Summary & Distribution Tables">
                    <i class="bi bi-filetype-csv" style="color:#059669;"></i>
                    <div>
                      <div style="font-weight:700;">Export CSV Spreadsheet</div>
                      <div style="font-size:0.7rem;color:var(--sub);">With KPI metrics &amp; graph distribution tables</div>
                    </div>
                  </a>
                  <a href="?export=livestock&format=xlsx" class="lv-menu-item" title="Download Excel .xlsx with Native Embedded Charts">
                    <i class="bi bi-file-earmark-excel-fill" style="color:#10b981;"></i>
                    <div>
                      <div style="font-weight:700;">Export Excel (.xlsx)</div>
                      <div style="font-size:0.7rem;color:var(--sub);">Formatted workbook with native chart drawing</div>
                    </div>
                  </a>
                  <a href="?export=livestock&format=pdf" class="lv-menu-item" title="Download Printable PDF Executive Report">
                    <i class="bi bi-file-earmark-pdf-fill" style="color:#ef4444;"></i>
                    <div>
                      <div style="font-weight:700;">Export PDF Report</div>
                      <div style="font-size:0.7rem;color:var(--sub);">Official herd summary with audit stamp</div>
                    </div>
                  </a>
                </div>
              </div>
              <button type="button" class="lv-btn-glass" onclick="navTo('incident')">
                <i class="bi bi-exclamation-triangle"></i> Report Issue
              </button>
            </div>
          </div>
        </div>

        <!-- 2. Metric KPI Dashboard Cards -->
        <div class="lv-kpi-grid">
          <div class="lv-kpi-card">
            <div class="lv-kpi-icon" style="background:#ecfdf5;color:#059669;">
              <i class="bi bi-boxes"></i>
            </div>
            <div class="lv-kpi-val">
              <span id="kpiTotalAnimals"><?= number_format($totalHeadCount) ?></span>
              <span style="font-size:0.8rem;color:var(--sub);font-weight:600;">heads</span>
            </div>
            <div class="lv-kpi-lbl">Total Head Count</div>
            <div class="lv-kpi-sub">
              <i class="bi bi-upc-scan" style="color:var(--acc);"></i>
              <span><?= count($flatLivestock) ?> tagged lots / records</span>
            </div>
          </div>

          <div class="lv-kpi-card">
            <div class="lv-kpi-icon" style="background:rgba(16,185,129,0.12);color:#10b981;">
              <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <div class="lv-kpi-val">
              <span><?= $healthRate ?>%</span>
            </div>
            <div class="lv-kpi-lbl">Health Index</div>
            <div class="lv-kpi-sub">
              <span class="lv-status-dot lv-status-healthy"></span>
              <span><?= number_format($healthyCount) ?> in optimal condition</span>
            </div>
          </div>

          <div class="lv-kpi-card">
            <div class="lv-kpi-icon" style="background:#fef2f2;color:#ef4444;">
              <i class="bi bi-shield-exclamation"></i>
            </div>
            <div class="lv-kpi-val" style="color:<?= $sickCount > 0 ? '#ef4444' : 'var(--txt)' ?>;">
              <span><?= number_format($sickCount) ?></span>
              <span style="font-size:0.8rem;color:var(--sub);font-weight:600;">head<?= $sickCount === 1 ? '' : 's' ?></span>
            </div>
            <div class="lv-kpi-lbl">Requires Attention</div>
            <div class="lv-kpi-sub" style="color:<?= $sickCount > 0 ? '#dc2626' : 'var(--sub)' ?>;">
              <?= $sickCount > 0 ? '<i class="bi bi-exclamation-circle-fill"></i> Flagged for treatment' : '<i class="bi bi-check-circle-fill" style="color:#10b981;"></i> No critical alerts' ?>
            </div>
          </div>

          <div class="lv-kpi-card">
            <div class="lv-kpi-icon" style="background:#eff6ff;color:#3b82f6;">
              <i class="bi bi-shield-check"></i>
            </div>
            <div class="lv-kpi-val">
              <span><?= $vaxRate ?>%</span>
            </div>
            <div class="lv-kpi-lbl">Vaccination Coverage</div>
            <div class="lv-kpi-sub">
              <i class="bi bi-check2-circle" style="color:#3b82f6;"></i>
              <span><?= number_format($vaxCompleteCount) ?> head<?= $vaxCompleteCount === 1 ? '' : 's' ?> fully vaccinated</span>
            </div>
          </div>
        </div>

        <?php if(empty($flatLivestock)): ?>
        <div class="card" style="padding:2.5rem 1.5rem;text-align:center;border-radius:18px;">
          <div style="width:72px;height:72px;margin:0 auto 1.25rem;border-radius:20px;background:#ecfdf5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:2.2rem;">
            <i class="bi bi-shield-plus"></i>
          </div>
          <h3 style="font-weight:800;color:var(--txt);margin-bottom:0.5rem;font-size:1.2rem;">No Livestock Recorded Yet</h3>
          <p style="font-size:0.85rem;color:var(--sub);max-width:440px;margin:0 auto 1.5rem;line-height:1.5;">
            <?= empty($farmsData) ? 'Please register your farm first. Once approved, you can start tracking and monitoring your animal registry.' : 'Your farm registry is ready. Add your first animal to track vaccinations, health condition, and weight records.' ?>
          </p>
          <?php if(empty($farmsData)): ?>
          <button class="btn btn-primary" onclick="navTo('farm-reg')"><i class="bi bi-house-add-fill"></i> Register Farm First</button>
          <?php else: ?>
          <button class="btn btn-primary" onclick="openModal('lvAddModal')"><i class="bi bi-plus-lg"></i> Add First Animal</button>
          <?php endif; ?>
        </div>
        <?php else: ?>

        <!-- Visual Herd Analytics & Graph Layout -->
        <div class="lv-charts-grid">
          <div class="lv-chart-card">
            <div class="lv-chart-header">
              <h3 class="lv-chart-title">
                <i class="bi bi-pie-chart-fill"></i> Herd Species Distribution
              </h3>
              <span class="lv-chart-badge"><?= count($speciesCounts) ?> Species Categories</span>
            </div>
            <div class="lv-chart-body">
              <canvas id="lvSpeciesChart"></canvas>
            </div>
          </div>
          <div class="lv-chart-card">
            <div class="lv-chart-header">
              <h3 class="lv-chart-title">
                <i class="bi bi-bar-chart-steps"></i> Health &amp; Vaccination Status
              </h3>
              <span class="lv-chart-badge">Biosecurity Breakdown</span>
            </div>
            <div class="lv-chart-body">
              <canvas id="lvHealthVaxChart"></canvas>
            </div>
          </div>
        </div>

        <!-- 3. Species Filter Ribbon -->
        <div class="lv-ribbon-wrap" id="lvSpeciesRibbon">
          <button type="button" class="lv-chip-btn active" data-species="all" onclick="filterBySpecies('all', this)">
            <span><i class="bi bi-tag-fill"></i> All Species</span>
            <span class="lv-chip-count"><?= count($flatLivestock) ?></span>
          </button>
          <?php foreach($allAnimalTypes as $at): 
            $atCount = 0;
            foreach($flatLivestock as $fl) {
              if (strcasecmp($fl['type'], $at) === 0) $atCount++;
            }
            if ($atCount === 0) continue;
            $ico = $animalEmoji[$at] ?? '<i class="bi bi-tag-fill"></i>';
          ?>
          <button type="button" class="lv-chip-btn" data-species="<?= strtolower($at) ?>" onclick="filterBySpecies('<?= strtolower($at) ?>', this)">
            <span><?= $ico ?> <?= $at ?></span>
            <span class="lv-chip-count"><?= $atCount ?></span>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- 4. Interactive Toolbar -->
        <div class="lv-toolbar-card">
          <div class="lv-search-box">
            <i class="bi bi-search"></i>
            <input type="text" class="lv-search-input" id="lvSearchInput" placeholder="Search Tag ID, breed, farm, notes..." oninput="filterLivestock()" autocomplete="off">
            <button type="button" class="lv-search-clear" id="lvSearchClear" onclick="clearLvSearch()" title="Clear search">
              <i class="bi bi-x-circle-fill"></i>
            </button>
          </div>

          <div class="lv-filters-row">
            <select class="lv-select" id="lvFarmFilter" onchange="filterLivestock()">
              <option value="all">All Farms (<?= count($farmsData) ?>)</option>
              <?php foreach($farmsData as $f): ?>
              <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
              <?php endforeach; ?>
            </select>

            <select class="lv-select" id="lvHealthFilter" onchange="filterLivestock()">
              <option value="all">All Health Conditions</option>
              <option value="Healthy"><span class="dot-sm" style="background:#10b981;width:8px;height:8px;border-radius:50%;display:inline-block;"></span> Healthy</option>
              <option value="Sick"><span class="dot-sm" style="background:#ef4444;width:8px;height:8px;border-radius:50%;display:inline-block;"></span> Sick / Treatment</option>
              <option value="Recovered"><span class="dot-sm" style="background:#3b82f6;width:8px;height:8px;border-radius:50%;display:inline-block;"></span> Recovered</option>
              <option value="Deceased"><span class="dot-sm" style="background:#94a3b8;width:8px;height:8px;border-radius:50%;display:inline-block;"></span> Deceased</option>
            </select>

            <div class="lv-view-toggles">
              <button type="button" class="lv-view-btn active" id="lvBtnGrid" onclick="setLvView('grid')" title="Card Grid View">
                <i class="bi bi-grid-fill"></i> Cards
              </button>
              <button type="button" class="lv-view-btn" id="lvBtnTable" onclick="setLvView('table')" title="Dense Table View">
                <i class="bi bi-table"></i> Table
              </button>
              <button type="button" class="lv-view-btn" id="lvBtnFarm" onclick="setLvView('farm')" title="Grouped by Farm">
                <i class="bi bi-folder2-open"></i> By Farm
              </button>
            </div>
          </div>
        </div>

        <!-- 5. View A: Card Grid View -->
        <div id="lvGridView" class="lv-grid">
          <?php foreach($flatLivestock as $lv): 
            $theme = getAnimalTheme($lv['type']);
            $h = $lv['healthStatus'] ?? 'Healthy';
            $v = $lv['vaccinationStatus'] ?? 'None';
            $pillClass = 'lv-pill-' . strtolower($h);
            $vClass = 'lv-vax-' . strtolower($v);
          ?>
          <div class="lv-card"
               data-id="<?= $lv['id'] ?>"
               data-tag="<?= htmlspecialchars(strtolower($lv['tagId'])) ?>"
               data-tag-raw="<?= htmlspecialchars($lv['tagId']) ?>"
               data-species="<?= htmlspecialchars(strtolower($lv['type'])) ?>"
               data-species-raw="<?= htmlspecialchars($lv['type']) ?>"
               data-breed="<?= htmlspecialchars(strtolower($lv['breed']??'')) ?>"
               data-breed-raw="<?= htmlspecialchars($lv['breed']??'') ?>"
               data-farm-id="<?= $lv['farmId'] ?>"
               data-farm-name="<?= htmlspecialchars(strtolower($lv['farmName'])) ?>"
               data-farm-name-raw="<?= htmlspecialchars($lv['farmName']) ?>"
               data-health="<?= htmlspecialchars($h) ?>"
               data-notes="<?= htmlspecialchars(strtolower($lv['notes']??'')) ?>"
               data-notes-raw="<?= htmlspecialchars($lv['notes']??'') ?>"
               data-qty="<?= (int)$lv['qty'] ?>"
               data-age="<?= htmlspecialchars($lv['age']??'') ?>"
               data-weight="<?= htmlspecialchars($lv['weight']??'') ?>">
            
            <div class="lv-card-top">
              <div class="lv-avatar" style="background:<?= $theme['bg'] ?>;color:<?= $theme['color'] ?>;">
                <?= $animalEmoji[$lv['type']] ?? '<i class="bi bi-tag-fill"></i>' ?>
              </div>
              <div class="lv-card-main">
                <div class="lv-card-title" title="<?= htmlspecialchars($lv['breed'] ?: $lv['type']) ?>">
                  <span><?= htmlspecialchars($lv['breed'] ?: $lv['type']) ?></span>
                  <span style="font-size:0.7rem;font-weight:600;color:var(--sub);">&bull; <?= htmlspecialchars($lv['type']) ?></span>
                </div>
                <div class="lv-farm-badge">
                  <i class="bi bi-geo-alt-fill" style="color:var(--acc);font-size:0.75rem;"></i>
                  <span><?= htmlspecialchars($lv['farmName']) ?></span>
                  <span class="badge <?= statusBadge($lv['farmStatus']) ?>" style="font-size:0.6rem;padding:1px 5px;"><?= $lv['farmStatus'] ?></span>
                </div>
              </div>
            </div>

            <div class="lv-tag-strip">
              <span class="lv-tag-chip" onclick="copyTagId('<?= htmlspecialchars(addslashes($lv['tagId'])) ?>', this)" title="Click to copy Tag ID">
                <i class="bi bi-tag-fill"></i>
                <?= htmlspecialchars($lv['tagId']) ?>
                <i class="bi bi-copy" style="font-size:0.7rem;opacity:0.6;margin-left:2px;"></i>
              </span>
              <div style="display:inline-flex;gap:4px;align-items:center;">
                <span class="badge" style="font-size:0.68rem;padding:3px 8px;border-radius:6px;font-weight:700;<?= ($lv['farming_type']??'Backyard')==='Commercial' ? 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;' : 'background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;' ?>">
                  <?= htmlspecialchars($lv['farming_type'] ?? 'Backyard') ?>
                </span>
                <span class="lv-qty-pill">
                  <i class="bi bi-collection"></i> <?= number_format($lv['qty']) ?> <?= (int)$lv['qty'] === 1 ? 'Head' : 'Heads' ?>
                </span>
              </div>
            </div>

            <div class="lv-vitals-grid">
              <div class="lv-vital-cell">
                <span class="lv-vital-lbl">Condition</span>
                <span class="lv-pill <?= $pillClass ?>">
                  <span class="lv-status-dot lv-status-<?= strtolower($h) ?>"></span>
                  <?= htmlspecialchars($h) ?>
                </span>
              </div>
              <div class="lv-vital-cell">
                <span class="lv-vital-lbl">Vaccine</span>
                <span class="lv-vax-tag <?= $vClass ?>">
                  <i class="bi <?= $v === 'Complete' ? 'bi-shield-check' : ($v === 'Partial' ? 'bi-shield-shaded' : 'bi-shield-x') ?>"></i>
                  <?= htmlspecialchars($v) ?>
                </span>
              </div>
              <div class="lv-vital-cell">
                <span class="lv-vital-lbl">Age</span>
                <span class="lv-vital-val"><?= !empty($lv['age']) ? ((float)$lv['age'] . ' yrs') : '<span style="color:var(--sub);font-weight:400;">  -  </span>' ?></span>
              </div>
              <div class="lv-vital-cell">
                <span class="lv-vital-lbl">Weight</span>
                <span class="lv-vital-val"><?= (!empty($lv['weight']) && (float)$lv['weight'] > 0) ? (number_format((float)$lv['weight'], 1) . ' kg') : '<span style="color:var(--sub);font-weight:400;">  -  </span>' ?></span>
              </div>
            </div>

            <?php if(!empty($lv['notes'])): ?>
            <div class="lv-notes-callout" title="Medical and observation notes">
              <i class="bi bi-journal-medical" style="color:#059669;margin-right:4px;"></i>
              <?= htmlspecialchars($lv['notes']) ?>
            </div>
            <?php endif; ?>

            <div class="lv-card-footer">
              <div style="font-size:0.7rem;color:var(--sub);">
                <i class="bi bi-clock-history"></i> <?= !empty($lv['createdAt']) ? date('M j, Y', strtotime($lv['createdAt'])) : 'Active' ?>
              </div>
              <div class="lv-card-btns">
                <button type="button" class="lv-action-btn health" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;" onclick="openHealthHistory(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>','<?= htmlspecialchars(addslashes($lv['breed'] ?: $lv['type'])) ?>')" title="Animal health &amp; treatment history">
                  <i class="bi bi-heart-pulse-fill"></i> Health
                </button>
                <?php if($h === 'Sick'): ?>
                <button type="button" class="lv-action-btn warn" onclick="reportIssueForAnimal(<?= $lv['farmId'] ?>, '<?= $lv['type'] ?>', '<?= htmlspecialchars(addslashes($lv['tagId'])) ?>', '<?= htmlspecialchars(addslashes($lv['breed']??'')) ?>')" title="Report health incident to DA">
                  <i class="bi bi-exclamation-triangle-fill"></i> Alert
                </button>
                <?php endif; ?>
                <button type="button" class="lv-action-btn" onclick="openEditLv(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>','<?= $lv['type'] ?>','<?= htmlspecialchars(addslashes($lv['breed']??'')) ?>',<?= (int)$lv['qty'] ?>,<?= (float)($lv['age']??0) ?>,<?= (float)($lv['weight']??0) ?>,'<?= $lv['healthStatus'] ?>','<?= $lv['vaccinationStatus'] ?>','<?= htmlspecialchars(addslashes($lv['notes']??'')) ?>','<?= htmlspecialchars(addslashes($lv['farming_type']??'Backyard')) ?>')" title="Edit details">
                  <i class="bi bi-pencil-square"></i> Edit
                </button>
                <button type="button" class="lv-action-btn danger" onclick="deleteLv(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>')" title="Delete animal record">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </div>

          </div>
          <?php endforeach; ?>
        </div>

        <!-- 6. View B: High-Density Table View -->
        <div id="lvTableView" class="lv-table-card" style="display:none;">
          <div class="lv-table-wrap">
            <table class="lv-table">
              <thead>
                <tr>
                  <th>Tag ID</th>
                  <th>Animal &amp; Breed</th>
                  <th>Farm</th>
                  <th style="text-align:center;">Qty</th>
                  <th>Condition</th>
                  <th>Vaccination</th>
                  <th>Age / Weight</th>
                  <th>Notes</th>
                  <th style="text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($flatLivestock as $lv): 
                  $theme = getAnimalTheme($lv['type']);
                  $h = $lv['healthStatus'] ?? 'Healthy';
                  $v = $lv['vaccinationStatus'] ?? 'None';
                ?>
                <tr class="lv-tbl-row"
                    data-tag="<?= htmlspecialchars(strtolower($lv['tagId'])) ?>"
                    data-species="<?= htmlspecialchars(strtolower($lv['type'])) ?>"
                    data-breed="<?= htmlspecialchars(strtolower($lv['breed']??'')) ?>"
                    data-farm-id="<?= $lv['farmId'] ?>"
                    data-farm-name="<?= htmlspecialchars(strtolower($lv['farmName'])) ?>"
                    data-health="<?= htmlspecialchars($h) ?>"
                    data-notes="<?= htmlspecialchars(strtolower($lv['notes']??'')) ?>">
                  <td>
                    <span class="lv-tag-chip" onclick="copyTagId('<?= htmlspecialchars(addslashes($lv['tagId'])) ?>', this)" title="Click to copy">
                      <?= htmlspecialchars($lv['tagId']) ?>
                      <i class="bi bi-copy" style="font-size:0.68rem;opacity:0.6;margin-left:2px;"></i>
                    </span>
                    <div style="margin-top:3px;">
                      <span class="badge" style="font-size:0.65rem;padding:2px 6px;border-radius:4px;font-weight:700;<?= ($lv['farming_type']??'Backyard')==='Commercial' ? 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;' : 'background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;' ?>">
                        <?= htmlspecialchars($lv['farming_type'] ?? 'Backyard') ?>
                      </span>
                    </div>
                  </td>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                      <span style="font-size:1.15rem;"><?= $animalEmoji[$lv['type']] ?? '<i class="bi bi-tag-fill"></i>' ?></span>
                      <div>
                        <div style="font-weight:700;color:var(--txt);"><?= htmlspecialchars($lv['breed'] ?: $lv['type']) ?></div>
                        <div style="font-size:0.7rem;color:var(--sub);"><?= htmlspecialchars($lv['type']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight:600;font-size:0.8rem;color:var(--txt);"><?= htmlspecialchars($lv['farmName']) ?></div>
                    <div style="font-size:0.7rem;color:var(--sub);"><?= htmlspecialchars($lv['farmType']) ?></div>
                  </td>
                  <td style="text-align:center;font-weight:800;color:var(--acc2);">
                    <?= number_format($lv['qty']) ?>
                  </td>
                  <td>
                    <span class="lv-pill lv-pill-<?= strtolower($h) ?>">
                      <span class="lv-status-dot lv-status-<?= strtolower($h) ?>"></span>
                      <?= htmlspecialchars($h) ?>
                    </span>
                  </td>
                  <td>
                    <span class="lv-vax-tag lv-vax-<?= strtolower($v) ?>">
                      <?= htmlspecialchars($v) ?>
                    </span>
                  </td>
                  <td style="font-size:0.75rem;color:var(--sub);">
                    <div><?= !empty($lv['age']) ? ((float)$lv['age'] . ' yrs') : '  -  ' ?></div>
                    <div><?= (!empty($lv['weight']) && (float)$lv['weight'] > 0) ? (number_format((float)$lv['weight'], 1) . ' kg') : '  -  ' ?></div>
                  </td>
                  <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:0.73rem;color:var(--sub);">
                    <?= !empty($lv['notes']) ? htmlspecialchars($lv['notes']) : '<span style="opacity:0.4;">  -  </span>' ?>
                  </td>
                  <td style="text-align:right;">
                    <div style="display:inline-flex;gap:4px;">
                      <button type="button" class="lv-action-btn health" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;" onclick="openHealthHistory(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>','<?= htmlspecialchars(addslashes($lv['breed'] ?: $lv['type'])) ?>')" title="Health history">
                        <i class="bi bi-heart-pulse-fill"></i>
                      </button>
                      <?php if($h === 'Sick'): ?>
                      <button type="button" class="lv-action-btn warn" onclick="reportIssueForAnimal(<?= $lv['farmId'] ?>, '<?= $lv['type'] ?>', '<?= htmlspecialchars(addslashes($lv['tagId'])) ?>', '<?= htmlspecialchars(addslashes($lv['breed']??'')) ?>')" title="Alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                      </button>
                      <?php endif; ?>
                      <button type="button" class="lv-action-btn" onclick="openEditLv(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>','<?= $lv['type'] ?>','<?= htmlspecialchars(addslashes($lv['breed']??'')) ?>',<?= (int)$lv['qty'] ?>,<?= (float)($lv['age']??0) ?>,<?= (float)($lv['weight']??0) ?>,'<?= $lv['healthStatus'] ?>','<?= $lv['vaccinationStatus'] ?>','<?= htmlspecialchars(addslashes($lv['notes']??'')) ?>','<?= htmlspecialchars(addslashes($lv['farming_type']??'Backyard')) ?>')">
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      <button type="button" class="lv-action-btn danger" onclick="deleteLv(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>')">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- 7. View C: Farm Grouped View -->
        <div id="lvFarmView" style="display:none;">
          <?php foreach($livestockByFarm as $fId => $fData):
            $fAnimals = $fData['livestock'];
            $farmTotal = array_sum(array_column($fAnimals, 'qty'));
          ?>
          <div class="lv-farm-card" data-farm-id="<?= $fId ?>">
            <div class="lv-farm-header">
              <div class="lv-farm-avatar">
                <?= strtoupper(substr($fData['farm']['name'], 0, 1)) ?>
              </div>
              <div style="flex:1;min-width:0;">
                <div class="lv-farm-title"><?= htmlspecialchars($fData['farm']['name']) ?></div>
                <div class="lv-farm-subtitle">
                  <span class="badge <?= statusBadge($fData['farm']['status']) ?>"><?= $fData['farm']['status'] ?></span>
                  <span style="font-size:0.73rem;color:var(--sub);"><?= htmlspecialchars($fData['farm']['type']) ?></span>
                  <?php if(!empty($fData['farm']['address'])): ?>
                  <span style="font-size:0.73rem;color:var(--sub);">&bull; <?= htmlspecialchars($fData['farm']['address']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="lv-farm-count-badge">
                <div class="lv-farm-count-num"><?= number_format($farmTotal) ?></div>
                <div class="lv-farm-count-lbl">Head<?= $farmTotal === 1 ? '' : 's' ?></div>
              </div>
            </div>

            <?php if(!empty($fAnimals)): ?>
            <div class="lv-grid" style="margin-bottom:0;">
              <?php foreach($fAnimals as $lv): 
                $theme = getAnimalTheme($lv['type']);
                $h = $lv['healthStatus'] ?? 'Healthy';
                $v = $lv['vaccinationStatus'] ?? 'None';
              ?>
              <div class="lv-card"
                   data-id="<?= $lv['id'] ?>"
                   data-tag="<?= htmlspecialchars(strtolower($lv['tagId'])) ?>"
                   data-species="<?= htmlspecialchars(strtolower($lv['type'])) ?>"
                   data-breed="<?= htmlspecialchars(strtolower($lv['breed']??'')) ?>"
                   data-farm-id="<?= $fId ?>"
                   data-farm-name="<?= htmlspecialchars(strtolower($fData['farm']['name'])) ?>"
                   data-health="<?= htmlspecialchars($h) ?>"
                   data-notes="<?= htmlspecialchars(strtolower($lv['notes']??'')) ?>">
                
                <div class="lv-card-top">
                  <div class="lv-avatar" style="background:<?= $theme['bg'] ?>;color:<?= $theme['color'] ?>;">
                    <?= $animalEmoji[$lv['type']] ?? '<i class="bi bi-tag-fill"></i>' ?>
                  </div>
                  <div class="lv-card-main">
                    <div class="lv-card-title"><?= htmlspecialchars($lv['breed'] ?: $lv['type']) ?></div>
                    <div style="font-size:0.72rem;color:var(--sub);"><?= htmlspecialchars($lv['type']) ?></div>
                  </div>
                </div>

                <div class="lv-tag-strip">
                  <span class="lv-tag-chip" onclick="copyTagId('<?= htmlspecialchars(addslashes($lv['tagId'])) ?>', this)">
                    <?= htmlspecialchars($lv['tagId']) ?>
                  </span>
                  <span class="lv-qty-pill"><?= number_format($lv['qty']) ?> Head</span>
                </div>

                <div class="lv-vitals-grid">
                  <div class="lv-vital-cell">
                    <span class="lv-vital-lbl">Condition</span>
                    <span class="lv-pill lv-pill-<?= strtolower($h) ?>">
                      <span class="lv-status-dot lv-status-<?= strtolower($h) ?>"></span>
                      <?= htmlspecialchars($h) ?>
                    </span>
                  </div>
                  <div class="lv-vital-cell">
                    <span class="lv-vital-lbl">Vaccine</span>
                    <span class="lv-vax-tag lv-vax-<?= strtolower($v) ?>"><?= htmlspecialchars($v) ?></span>
                  </div>
                </div>

                <div class="lv-card-footer">
                  <span style="font-size:0.7rem;color:var(--sub);">Lot #<?= $lv['id'] ?></span>
                  <div class="lv-card-btns">
                    <button type="button" class="lv-action-btn" onclick="openEditLv(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>','<?= $lv['type'] ?>','<?= htmlspecialchars(addslashes($lv['breed']??'')) ?>',<?= (int)$lv['qty'] ?>,<?= (float)($lv['age']??0) ?>,<?= (float)($lv['weight']??0) ?>,'<?= $lv['healthStatus'] ?>','<?= $lv['vaccinationStatus'] ?>','<?= htmlspecialchars(addslashes($lv['notes']??'')) ?>','<?= htmlspecialchars(addslashes($lv['farming_type']??'Backyard')) ?>')">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button type="button" class="lv-action-btn danger" onclick="deleteLv(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>')">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:1.5rem 1rem;background:#f8fafc;border-radius:12px;border:1px dashed var(--border);">
              <p style="font-size:0.8rem;color:var(--sub);margin-bottom:0.75rem;">No livestock assigned to this farm yet.</p>
              <button type="button" class="btn btn-primary" style="font-size:0.78rem;padding:0.45rem 0.9rem;" onclick="openAddLvForFarm(<?= $fId ?>)">
                <i class="bi bi-plus-circle"></i> Add Animal to This Farm
              </button>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- 8. No Matching Results State -->
        <div id="lvNoResults" class="card" style="display:none;text-align:center;padding:2.5rem 1rem;border-radius:18px;">
          <div style="width:60px;height:60px;margin:0 auto 1rem;background:#f1f5f9;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--sub);font-size:1.6rem;">
            <i class="bi bi-search"></i>
          </div>
          <h4 style="font-weight:800;color:var(--txt);margin-bottom:0.35rem;">No Matching Animals Found</h4>
          <p style="font-size:0.82rem;color:var(--sub);max-width:380px;margin:0 auto 1.25rem;">
            No animals matched your search query or active filters. Try adjusting your search term or reset all filters.
          </p>
          <button type="button" class="btn btn-primary" style="font-size:0.8rem;padding:0.5rem 1rem;" onclick="resetLvFilters()">
            <i class="bi bi-arrow-counterclockwise"></i> Reset Filters
          </button>
        </div>

        <?php endif; ?>
      </div>

      <!-- ===== INCIDENT ===== -->
      <div id="pg-incident" class="page">
        <div class="incident-hdr">
          <h2><i class="bi bi-exclamation-octagon-fill"></i> Report Livestock Incident</h2>
          <p>Your report will automatically alert DA officers. Critical disease reports also notify nearby farms.</p>
        </div>
        <div class="alert-banner">
          <i class="bi bi-lightning-charge" style="font-size:1.35rem;color:#d97706;flex-shrink:0;"></i>
          <div style="font-size:.8rem;color:#92400e;line-height:1.5;"><strong>Auto-Alert:</strong> ALL incidents notify DA officers immediately. Critical reports (ASF, FMD, Avian Flu) also alert farms within 10km.</div>
        </div>
        <form id="incidentForm" autocomplete="off">
          <input type="hidden" name="action" value="report_incident">
          <input type="hidden" id="incPhoto" name="photo_data">
          <input type="hidden" id="incLat" name="latitude">
          <input type="hidden" id="incLng" name="longitude">
          <input type="hidden" id="incGpsAcc" name="gps_accuracy">
          <input type="hidden" id="incGpsSource" name="gps_source">
          <input type="hidden" id="incSeverityVal" name="severity" value="Low">
          <div class="card">
            <h3 class="card-title"><i class="bi bi-house-check"></i> Farm &amp; Incident Type</h3>
            <div class="form-group"><label class="form-label">Farm *</label>
              <select class="form-select" name="farm_id" required>
                <option value="">Select your farm...</option>
                <?php foreach($farmsData as $f): ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> (<?= $f['type'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label class="form-label">Incident Type *</label>
              <select class="form-select" name="incident_type" required onchange="updateDiseaseTags(this.value)">
                <option value="">Select type...</option>
                <option value="Sick">Cattle Sick Animals</option>
                <option value="Dead"><i class="bi bi-x-circle-fill"></i> Dead Animals</option>
                <option value="Disease">Disease Outbreak</option>
                <option value="Stray">Swine Stray Animals</option>
                <option value="Others"><i class="bi bi-clipboard2-check"></i> Others</option>
              </select>
            </div>
            <div class="disease-tags" id="diseaseTags" style="display:none;">
              <span class="disease-tag" onclick="toggleDTag(this,'ASF')">ASF</span>
              <span class="disease-tag" onclick="toggleDTag(this,'FMD')">FMD</span>
              <span class="disease-tag" onclick="toggleDTag(this,'Avian Flu')">Avian Flu</span>
              <span class="disease-tag" onclick="toggleDTag(this,'PRRS')">PRRS</span>
              <span class="disease-tag" onclick="toggleDTag(this,'Classical Swine Fever')">Classical Swine Fever</span>
            </div>
          </div>
          <div class="card">
            <h3 class="card-title"><i class="bi bi-exclamation-triangle"></i> Severity &amp; Details</h3>
            <div class="form-group"><label class="form-label">Severity Level *</label>
              <div class="severity-grid">
                <div class="severity-card active" onclick="selectSeverity(this,'Low')"><span class="severity-icon"><span class="dot-sm" style="background:#10b981;width:8px;height:8px;border-radius:50%;display:inline-block;"></span></span><div>Low</div><small>Minor issue</small></div>
                <div class="severity-card" onclick="selectSeverity(this,'Medium')"><span class="severity-icon"><span class="dot-sm" style="background:#f59e0b;width:8px;height:8px;border-radius:50%;display:inline-block;"></span></span><div>Medium</div><small>Moderate</small></div>
                <div class="severity-card" onclick="selectSeverity(this,'High')"><span class="severity-icon"><span class="dot-sm" style="background:#f97316;width:8px;height:8px;border-radius:50%;display:inline-block;"></span></span><div>High</div><small>Serious</small></div>
                <div class="severity-card" onclick="selectSeverity(this,'Critical')"><span class="severity-icon"><span class="dot-sm" style="background:#ef4444;width:8px;height:8px;border-radius:50%;display:inline-block;"></span></span><div>Critical</div><small>Emergency</small></div>
              </div>
            </div>
            <div class="form-group"><label class="form-label">Affected Livestock Type</label>
              <select class="form-select" name="livestock_type">
                <option value="">All types</option>
                <?php foreach($allAnimalTypes as $at): ?>
                <option><?= $at ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label class="form-label">Symptoms Observed</label><textarea class="form-input" name="symptoms" rows="2" placeholder="Fever, coughing, diarrhea..."></textarea></div>
            <div class="form-group"><label class="form-label">Title *</label><input type="text" class="form-input" name="title" required placeholder="e.g. '5 pigs died suddenly with fever'"></div>
            <div class="form-group"><label class="form-label">Detailed Description *</label><textarea class="form-input" name="description" rows="3" required placeholder="Describe what you observed, when it started..."></textarea></div>
          </div>
          <div class="card">
            <h3 class="card-title"><i class="bi bi-geo-alt"></i> Location &amp; Evidence</h3>
            <div class="capture-zone" onclick="openIncCam()">
              <i class="bi bi-camera-video" style="font-size:2.5rem;color:#6b7280;display:block;margin-bottom:.75rem;"></i>
              <h4 style="color:#374151;margin-bottom:.375rem;font-size:.9rem;">Capture Photo + GPS <i class="bi bi-camera-fill"></i><i class="bi bi-geo-alt-fill"></i></h4>
              <p style="color:#6b7280;font-size:.8rem;margin:0;">Tap to take photo of affected animals/location</p>
              <div id="incGpsBadge" style="display:none;margin-top:.75rem;background:#d1fae5;color:#065f46;padding:.4rem .875rem;border-radius:8px;font-size:.78rem;font-weight:600;align-items:center;gap:.375rem;justify-content:center;"><i class="bi bi-geo-alt-fill"></i> <span id="incGpsText">GPS Captured</span></div>
            </div>
            <!-- Captured/Uploaded Photo Preview -->
            <div id="incPhotoPreview" style="display:none;margin-top:.75rem;align-items:center;gap:.75rem;background:#f3f4f6;padding:.6rem .85rem;border-radius:8px;border:1px solid #e5e7eb;">
              <img id="incPhotoThumb" src="" alt="Incident Photo" style="width:52px;height:52px;object-fit:cover;border-radius:6px;border:1px solid #d1d5db;">
              <div style="flex:1;font-size:.82rem;color:#374151;line-height:1.4;">
                <strong>Photo Attached</strong><br>
                <span style="color:#059669;font-size:.75rem;"><i class="bi bi-check-circle-fill"></i> Optimized &amp; Geotagged</span>
              </div>
              <button type="button" class="btn btn-secondary" style="padding:.25rem .6rem;font-size:.78rem;" onclick="removeIncPhoto()"><i class="bi bi-trash"></i> Remove</button>
            </div>
            <div class="gps-warn-box" id="incGpsWarn"></div>
            <div class="gps-picker-wrap" id="incMapPickerWrap">
              <div class="gps-picker-hdr">
                <strong><i class="bi bi-pin-map-fill" style="color:#dc2626;"></i> Pin Exact Incident Location <span id="incMapEngineBadge" style="font-size:0.68rem;padding:2px 7px;border-radius:10px;background:#e0f2fe;color:#0284c7;font-weight:700;display:inline-flex;align-items:center;gap:3px;"><i class="bi bi-google"></i> Google Maps</span></strong>
                <div class="gps-picker-btns">
                  <button type="button" class="gps-picker-btn" onclick="togglePickerBasemap('incident')"><i class="bi bi-layers"></i> <span id="incBasemapLbl">Street</span></button>
                  <button type="button" class="gps-picker-btn" onclick="pickerUseMyLocation('incident')"><i class="bi bi-crosshair"></i> Use my location</button>
                </div>
              </div>
              <div class="gps-picker-search-bar">
                <div style="position:relative;flex:1;">
                  <i class="bi bi-search" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.8rem;"></i>
                  <input type="text" id="incMapSearchInput" class="gps-picker-search-input" placeholder="Search municipality, barangay, or landmark..." onkeydown="if(event.key==='Enter'){event.preventDefault();searchPickerLocation('incident');}">
                </div>
                <button type="button" class="gps-picker-btn gps-picker-search-btn" onclick="searchPickerLocation('incident')"><i class="bi bi-search"></i> Search</button>
              </div>
              <div class="gps-picker-chips">
                <span style="color:#64748b;font-weight:600;white-space:nowrap;font-size:0.72rem;"><i class="bi bi-geo"></i> 1st District:</span>
                <button type="button" class="gps-chip-btn" onclick="jumpPickerTo('incident', 13.8189, 122.7911, 'Ragay')">Ragay</button>
                <button type="button" class="gps-chip-btn" onclick="jumpPickerTo('incident', 13.9167, 122.6000, 'Del Gallego')">Del Gallego</button>
                <button type="button" class="gps-chip-btn" onclick="jumpPickerTo('incident', 13.7700, 122.9800, 'Sipocot')">Sipocot</button>
                <button type="button" class="gps-chip-btn" onclick="jumpPickerTo('incident', 13.8800, 122.8700, 'Lupi')">Lupi</button>
                <button type="button" class="gps-chip-btn" onclick="jumpPickerTo('incident', 13.7167, 123.0167, 'Cabusao')">Cabusao</button>
              </div>
              <div style="position:relative;">
                <div class="gps-map-hint"><i class="bi bi-hand-index-thumb"></i> Tap map or drag pin to relocate</div>
                <div id="incPickerMap" class="gps-picker-map"></div>
              </div>
              <div class="gps-picker-bar">
                <span class="gps-picker-coords" id="incPickerCoords">Drag marker to location</span>
                <div class="gps-picker-btns">
                  <button type="button" class="gps-picker-btn" onclick="closeLocationPicker('incident')">Close</button>
                  <button type="button" class="gps-picker-btn prim" onclick="confirmLocationPin('incident')"><i class="bi bi-check-lg"></i> Confirm Pin</button>
                </div>
              </div>
            </div>
            <div style="text-align:center;margin-top:.6rem;display:flex;justify-content:center;gap:.5rem;flex-wrap:wrap;">
              <button type="button" class="btn btn-secondary" style="font-size:.82rem;" onclick="document.getElementById('incFileUpload').click()"><i class="bi bi-upload"></i> Upload Photo Instead</button>
              <button type="button" class="btn btn-secondary" style="font-size:.82rem;" onclick="openLocationPicker('incident', null, 'Drag the marker to the exact incident location inside Camarines Sur 1st District.')"><i class="bi bi-pin-map-fill"></i> Pin / Refine on Map</button>
              <input type="file" id="incFileUpload" accept="image/*" style="display:none;" onchange="handleIncUpload(event)">
            </div>
          </div>
          <div style="display:flex;gap:.75rem;margin-top:.5rem;">
            <button type="button" class="btn btn-secondary" style="flex:1;" onclick="resetIncForm()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            <button type="submit" class="btn-submit" id="incSubmitBtn" style="flex:2;background:linear-gradient(135deg,var(--danger),#dc2626);">
              <i class="bi bi-send-check"></i> Send Alert
            </button>
          </div>
        </form>
      </div>

      <!-- ===== NOTIFICATIONS ===== -->
      <div id="pg-notifications" class="page">
        <div class="card" style="box-shadow:0 1px 3px rgba(0,0,0,0.06);">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:1.25rem;">
            <div>
              <h3 class="card-title" style="margin-bottom:4px;display:flex;align-items:center;gap:8px;">
                <span>Notifications & Advisories</span>
                <span id="notifUnreadBadgePill" class="badge" style="<?= $unreadCount > 0 ? '' : 'display:none;' ?>background:var(--danger);color:#fff;font-size:.72rem;padding:.2rem .55rem;border-radius:999px;"><?= $unreadCount ?> Unread</span>
              </h3>
              <p style="font-size:.8rem;color:var(--sub);margin:0;">Real-time biosecurity disease alerts, DA farm certifications, and veterinary advisories.</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <button type="button" class="btn" onclick="sendTestNotification()" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;font-size:.78rem;padding:.45rem .85rem;border-radius:10px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                <i class="bi bi-bell-plus"></i> <span>Send Test Alert</span>
              </button>
              <button type="button" class="btn btn-primary" onclick="markAllNotifsRead()" style="font-size:.78rem;padding:.45rem .85rem;border-radius:10px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                <i class="bi bi-check2-all"></i> <span>Mark All as Read</span>
              </button>
              <button type="button" class="btn" onclick="clearAllNotifs()" style="background:#fff;border:1px solid #fecaca;color:#dc2626;font-size:.78rem;padding:.45rem .85rem;border-radius:10px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;transition:all .15s ease;" title="Delete all notifications">
                <i class="bi bi-trash3"></i> <span>Delete All</span>
              </button>
            </div>
          </div>

          <!-- Filter tabs -->
          <div class="notif-filters" style="display:flex;gap:8px;margin-bottom:1.25rem;overflow-x:auto;padding-bottom:4px;">
            <button type="button" class="notif-tab active" data-filter="all" onclick="filterNotifs('all')">All (<span id="notifCountAll"><?= count($notificationsData) ?></span>)</button>
            <button type="button" class="notif-tab" data-filter="unread" onclick="filterNotifs('unread')">Unread (<span id="notifCountUnread"><?= $unreadCount ?></span>)</button>
            <button type="button" class="notif-tab" data-filter="alerts" onclick="filterNotifs('alerts')"><i class="bi bi-exclamation-triangle-fill" style="color:var(--danger);"></i> Disease Alerts</button>
            <button type="button" class="notif-tab" data-filter="farms" onclick="filterNotifs('farms')"><i class="bi bi-patch-check-fill" style="color:var(--acc);"></i> Farm Approvals</button>
          </div>

          <div id="notifListContainer">
            <?php if(!empty($notificationsData)): ?>
              <?php foreach($notificationsData as $notif):
                $isCrit = in_array($notif['type'],['CRITICAL_ALERT','OUTBREAK_WARNING']);
                $iconClass = $isCrit ? 'exclamation-triangle-fill' : ($notif['type']==='FARM_APPROVED' ? 'patch-check-fill' : ($notif['type']==='FARM_REJECTED' ? 'x-circle-fill' : 'bell-fill'));
                $iconBg = $isCrit ? 'var(--danger)' : ($notif['type']==='FARM_APPROVED' ? 'var(--acc)' : ($notif['type']==='FARM_REJECTED' ? '#ea580c' : '#3b82f6'));
                $badgeBg = $isCrit ? 'background:#fee2e2;color:#991b1b;' : ($notif['priority']==='High' ? 'background:#ffedd5;color:#9a3412;' : 'background:#e0f2fe;color:#075985;');
              ?>
              <div class="notif-item <?= empty($notif['is_read']) ? 'unread' : 'read' ?> <?= $isCrit ? 'is-alert' : '' ?>" id="notif-item-<?= $notif['id'] ?>" data-id="<?= $notif['id'] ?>" data-read="<?= $notif['is_read'] ?>" data-type="<?= $notif['type'] ?>" onclick="markNotifRead(<?= $notif['id'] ?>)">
                <div class="notif-icon" style="background:<?= $iconBg ?>;">
                  <i class="bi bi-<?= $iconClass ?>"></i>
                </div>
                <div style="flex:1;min-width:0;">
                  <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                    <div style="font-weight:700;font-size:.875rem;color:var(--pri);display:flex;align-items:center;gap:6px;">
                      <span><?= htmlspecialchars($notif['title']) ?></span>
                      <?php if(empty($notif['is_read'])): ?><span class="notif-dot <?= $isCrit ? 'alert' : '' ?>"></span><?php endif; ?>
                    </div>
                    <span class="badge" style="<?= $badgeBg ?>font-size:.65rem;font-weight:700;padding:.15rem .45rem;border-radius:6px;"><?= htmlspecialchars($notif['priority'] ?: 'Normal') ?></span>
                  </div>
                  <div style="font-size:.8rem;color:var(--text);margin-top:.3rem;line-height:1.45;word-break:break-word;">
                    <?= nl2br(htmlspecialchars($notif['message'])) ?>
                  </div>
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-top:.5rem;gap:8px;flex-wrap:wrap;">
                    <div style="font-size:.7rem;color:var(--sub);"><i class="bi bi-clock"></i> <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></div>
                    <div style="display:flex;align-items:center;gap:6px;">
                      <?php if(empty($notif['is_read'])): ?>
                      <button type="button" class="notif-action-btn mark-read-btn" onclick="markNotifRead(<?= $notif['id'] ?>, event)" title="Mark as Read"><i class="bi bi-check2"></i> Mark read</button>
                      <?php endif; ?>
                      <button type="button" class="notif-action-btn delete-btn" onclick="deleteNotif(<?= $notif['id'] ?>, event)" title="Delete Notification"><i class="bi bi-trash"></i> Delete</button>
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-state" id="notifEmptyState"><i class="bi bi-bell-slash"></i><h4>No notifications yet</h4><p style="font-size:.8rem;color:var(--sub);">You will receive updates when disease alerts are issued or farm applications are processed.</p></div>
            <?php endif; ?>
          </div>
          <div id="notifFilteredEmpty" class="empty-state" style="display:none;padding:2rem 1rem;"><i class="bi bi-funnel"></i><h4>No notifications matching this filter</h4></div>
        </div>
      </div>

      <!-- ===== CHAT ===== -->
      <div id="pg-chat" class="page">
        <div class="chat-shell">
          <!-- LEFT SIDEBAR: CONTACTS -->
          <aside class="chat-sidebar" id="chatListView">
            <div class="chat-sidebar-hdr">
              <div class="chat-hdr-title">
                <i class="bi bi-chat-dots-fill" style="color:var(--acc);"></i>
                <span>Farmer Community</span>
              </div>
              <span class="chat-count-pill" id="chatContactsCount">0 farmers</span>
            </div>
            
            <div class="chat-search-wrap">
              <div class="chat-search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="farmerSearch" class="chat-search-input" placeholder="Search farmers..." oninput="filterFarmers(this.value)">
              </div>
            </div>

            <div class="chat-farmer-list" id="farmerList">
              <div style="padding:2.5rem 1rem;text-align:center;color:var(--sub);">
                <i class="bi bi-arrow-repeat" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                <p style="font-size:.85rem;">Loading farmers...</p>
              </div>
            </div>
          </aside>

          <!-- RIGHT PANE: CONVERSATION -->
          <section class="chat-conv-view" id="chatConvView">
            <!-- No Conversation Selected (Desktop Placeholder) -->
            <div class="chat-no-conv" id="chatNoConvView">
              <div class="chat-no-conv-icon">
                <i class="bi bi-chat-quote-fill"></i>
              </div>
              <h3>Select a Conversation</h3>
              <p>Connect and chat directly with fellow livestock farmers to coordinate, ask advice, and share experiences across Camarines Sur.</p>
            </div>

            <!-- Active Conversation Container -->
            <div class="chat-active-pane" id="chatActivePane">
              <div class="chat-hdr-mini">
                <div class="chat-hdr-left">
                  <button type="button" class="chat-back" onclick="closeChatConv()" title="Back to contacts"><i class="bi bi-arrow-left"></i></button>
                  <div class="chat-hdr-avatar" id="convAvatar">?</div>
                  <div style="min-width:0;">
                    <div class="chat-hdr-name">
                      <span id="convName">  -  </span>
                      <span class="role-tag-badge" id="convRoleBadge"></span>
                    </div>
                    <div class="chat-hdr-status">
                      <span class="status-dot"></span>
                      <span id="convStatus">Active now</span>
                    </div>
                  </div>
                </div>
                <div class="chat-hdr-actions">
                  <button type="button" class="call-btn-sm voice-btn" onclick="initiateCall('voice')" title="Voice call"><i class="bi bi-telephone-fill"></i></button>
                  <button type="button" class="call-btn-sm video-btn" onclick="initiateCall('video')" title="Video call"><i class="bi bi-camera-video-fill"></i></button>
                </div>
              </div>

              <!-- Messages Container -->
              <div class="chat-messages-area" id="convMessages"></div>

              <!-- Quick tags -->
              <div class="chat-quick-tags">
                <button type="button" class="quick-chip" onclick="quickInsertTag('Farm update: ')"> Farm update</button>
                <button type="button" class="quick-chip" onclick="quickInsertTag('<i class="bi bi-exclamation-octagon-fill"></i> Animal Health Concern: ')"><i class="bi bi-exclamation-octagon-fill"></i> Health concern</button>
                <button type="button" class="quick-chip" onclick="quickInsertTag('<i class="bi bi-clipboard2-check"></i> Inquiry on Farm Registration: ')"><i class="bi bi-clipboard2-check"></i> Registration Inquiry</button>
                <button type="button" class="quick-chip" onclick="quickInsertTag('<i class="bi bi-hand-thumbs-up-fill"></i> Good day! ')"><i class="bi bi-hand-thumbs-up-fill"></i> Good day</button>
              </div>

              <!-- Media Preview Bar (when image is chosen) -->
              <div class="chat-media-preview-bar" id="chatMediaPreviewBar">
                <img id="chatMediaThumb" class="chat-preview-thumb" src="" alt="Selected photo">
                <div class="chat-preview-info">
                  <strong id="chatMediaName" style="color:var(--txt);display:block;">Photo attached</strong>
                  <span>Ready to send with your message</span>
                </div>
                <button type="button" class="chat-preview-remove" onclick="removePendingMedia()" title="Remove image">
                  <i class="bi bi-x-circle-fill"></i>
                </button>
              </div>

              <!-- Input Bar -->
              <div class="chat-input-bar">
                <input type="file" id="chatFileInput" accept="image/*" style="display:none;" onchange="handleChatMediaSelect(event)">
                <button type="button" class="chat-attach-btn" onclick="triggerChatMedia()" title="Attach photo">
                  <i class="bi bi-image"></i>
                </button>
                <textarea class="chat-input" id="chatInput" rows="1" placeholder="Type a message..." onkeydown="handleChatKey(event)" oninput="autoResize(this)"></textarea>
                <button type="button" class="chat-send-btn" onclick="sendChatMessage()" title="Send message">
                  <i class="bi bi-send-fill"></i>
                </button>
              </div>
            </div>
          </section>
        </div>
      </div>

      <!-- ===== UNIFIED FARMER ASSISTANCE REQUEST FORM (VET & SLAUGHTER) ===== -->
      <div id="pg-vet-request" class="page">
        <div class="card" style="padding:1.25rem;">
          <!-- Top Header -->
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem;">
            <div>
              <h2 style="font-size:1.3rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:.5rem;margin:0;">
                <i class="bi bi-ui-checks-grid" style="color:#059669;"></i> Request Assistance &bull; Official Services
              </h2>
              <p style="margin:.25rem 0 0;font-size:.82rem;color:var(--sub);">
                Unified Assistance Portal for registered farmers. Select either <b>Veterinary Assistance</b> or <b>Slaughter Assistance</b>. Form details automatically transfer to the Official Portal for Veterinary Health Certificate (VHC) and Certificate of Inspection (COI) issuance.
              </p>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
              <span style="background:#ecfdf5;color:#065f46;padding:.35rem .85rem;border-radius:9999px;font-size:.78rem;font-weight:700;border:1px solid #a7f3d0;">
                <i class="bi bi-shield-check"></i> DA &bull; VHC &amp; COI Integrated
              </span>
            </div>
          </div>

          <!-- Top Navigation Switcher -->
          <div style="display:flex;gap:.6rem;margin-bottom:1.25rem;border-bottom:2px solid #e2e8f0;padding-bottom:.6rem;flex-wrap:wrap;">
            <button type="button" id="btnTabNewAssist" onclick="switchAssistanceTab('new')" class="btn btn-primary" style="padding:.45rem 1rem;font-size:.84rem;font-weight:700;display:inline-flex;align-items:center;gap:.4rem;">
              <i class="bi bi-plus-circle-fill"></i> New Assistance Request
            </button>
            <button type="button" id="btnTabHistoryAssist" onclick="switchAssistanceTab('history')" class="btn btn-secondary" style="padding:.45rem 1rem;font-size:.84rem;font-weight:700;display:inline-flex;align-items:center;gap:.4rem;">
              <i class="bi bi-card-checklist"></i> My Requests &amp; Issued Certificates (<?= count($assistanceRequests) ?>)
            </button>
          </div>

          <!-- PANE 1: UNIFIED FORM -->
          <div id="assistPaneNew">
            <form id="unifiedAssistanceForm" onsubmit="handleAssistancePreSubmit(event)" enctype="multipart/form-data">
              <input type="hidden" name="action" value="submit_assistance_request">
              <input type="hidden" name="assistance_type" id="hidden_assistance_type" value="Veterinary Assistance">

              <!-- ================= SECTION B. TYPE OF ASSISTANCE REQUESTED ================= -->
              <div style="background:#fff;border:2px solid #e2e8f0;border-radius:14px;padding:1.15rem;margin-bottom:1.25rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;">
                  <h3 style="font-size:.96rem;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:.45rem;">
                    <span style="width:24px;height:24px;border-radius:50%;background:#059669;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;">B</span>
                    Select Type of Assistance Requested *
                  </h3>
                  <small style="color:#64748b;font-size:.76rem;">Choose assistance required</small>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;">
                  <!-- Card 1: Veterinary Assistance -->
                  <div id="cardAssistVet" onclick="setAssistanceType('Veterinary Assistance')" style="border:2px solid #059669;background:#f0fdf4;border-radius:12px;padding:1rem;cursor:pointer;transition:all .2s;position:relative;">
                    <div style="display:flex;align-items:flex-start;gap:.75rem;">
                      <div style="width:42px;height:42px;border-radius:10px;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.35rem;flex-shrink:0;">
                        <i class="bi bi-bandaid-fill"></i>
                      </div>
                      <div>
                        <div style="display:flex;align-items:center;gap:.4rem;">
                          <strong style="font-size:.95rem;color:#065f46;">Veterinary Assistance</strong>
                          <span id="badgeActiveVet" style="background:#059669;color:#fff;font-size:.65rem;font-weight:800;padding:1px 6px;border-radius:99px;">ACTIVE</span>
                        </div>
                        <p style="margin:.25rem 0 0;font-size:.78rem;color:#334155;line-height:1.4;">
                          Clinical diagnosis, consultations, vaccinations, deworming, disease treatment, farm health inspections, and <b>Veterinary Health Certificate (VHC)</b> preparation.
                        </p>
                      </div>
                    </div>
                  </div>

                  <!-- Card 2: Slaughter Assistance -->
                  <div id="cardAssistSlaughter" onclick="setAssistanceType('Slaughter Assistance')" style="border:1.5px solid #cbd5e1;background:#fff;border-radius:12px;padding:1rem;cursor:pointer;transition:all .2s;position:relative;">
                    <div style="display:flex;align-items:flex-start;gap:.75rem;">
                      <div style="width:42px;height:42px;border-radius:10px;background:#e2e8f0;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:1.35rem;flex-shrink:0;" id="iconBoxSlaughter">
                        <i class="bi bi-receipt-cutoff"></i>
                      </div>
                      <div>
                        <div style="display:flex;align-items:center;gap:.4rem;">
                          <strong style="font-size:.95rem;color:#0f172a;" id="titleSlaughter">Slaughter Assistance</strong>
                          <span id="badgeActiveSlaughter" style="display:none;background:#db2777;color:#fff;font-size:.65rem;font-weight:800;padding:1px 6px;border-radius:99px;">ACTIVE</span>
                        </div>
                        <p style="margin:.25rem 0 0;font-size:.78rem;color:#64748b;line-height:1.4;">
                          Mandatory slaughter declarations, ante-mortem inspection, livestock transport clearances, and official <b>Certificate of Inspection (COI)</b> preparation.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ================= SECTION A. FARMER INFORMATION ================= -->
              <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:1.15rem;margin-bottom:1.25rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;flex-wrap:wrap;gap:.4rem;">
                  <h3 style="font-size:.96rem;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:.45rem;">
                    <span style="width:24px;height:24px;border-radius:50%;background:#059669;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;">A</span>
                    Farmer &amp; Account Information
                  </h3>
                  <span style="background:#e0f2fe;color:#0369a1;font-size:.72rem;font-weight:700;padding:.2rem .6rem;border-radius:99px;">
                    <i class="bi bi-person-check-fill"></i> Auto-retrieved from Registered Account
                  </span>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.85rem;">
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Farmer ID</label>
                    <input type="text" class="form-input" value="FARMER-<?= str_pad($userId, 5, '0', STR_PAD_LEFT) ?>" readonly style="background:#e2e8f0;font-weight:700;color:#1e293b;cursor:not-allowed;">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Full Name of Farmer / Owner *</label>
                    <input type="text" name="farmer_name" required class="form-input" value="<?= htmlspecialchars(trim(($profileData['firstName']??'').' '.($profileData['lastName']??''))) ?>" placeholder="Owner full name">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Mobile / Contact Number *</label>
                    <input type="text" name="farmer_contact" required class="form-input" value="<?= htmlspecialchars($profileData['mobile'] ?? '') ?>" placeholder="e.g. 09171234567">
                  </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.85rem;margin-top:.85rem;">
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Barangay *</label>
                    <input type="text" name="farmer_barangay" required class="form-input" value="<?= htmlspecialchars($fBarangay ?: ($primaryFarm['pet_barangay'] ?? 'Poblacion')) ?>" placeholder="Barangay">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Municipality *</label>
                    <select name="farmer_municipality" class="form-input" required>
                      <?php foreach(['Ragay','Sipocot','Lupi','Del Gallego','Cabusao'] as $lgu): ?>
                        <option value="<?= $lgu ?>" <?= (strcasecmp($farmerMuni, $lgu) === 0 ? 'selected' : '') ?>><?= $lgu ?>, Camarines Sur</option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Complete Farm / Residence Address *</label>
                    <input type="text" name="farmer_address" required class="form-input" value="<?= htmlspecialchars($profileData['address'] ?: ($primaryFarm['address'] ?? ($fBarangay.', '.$farmerMuni.', Camarines Sur'))) ?>" placeholder="House/Sitio, Street, Barangay">
                  </div>
                </div>
              </div>

              <!-- ================= SECTION C. LIVESTOCK INFORMATION ================= -->
              <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.15rem;margin-bottom:1.25rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;flex-wrap:wrap;gap:.4rem;">
                  <h3 style="font-size:.96rem;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:.45rem;">
                    <span style="width:24px;height:24px;border-radius:50%;background:#059669;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;">C</span>
                    Livestock Information (VHC &amp; COI Reference)
                  </h3>
                  <small style="color:#64748b;font-size:.76rem;">Required for Official VHC &amp; COI Certificate Encoding</small>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.85rem;">
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Kind of Animal / Species *</label>
                    <select name="animal_species" id="assist_species" class="form-input" onchange="handleAssistSpeciesChange(this.value)" required>
                      <option value="Swine">Swine (Baboy / Pigs)</option>
                      <option value="Cattle">Cattle (Baka / Cow)</option>
                      <option value="Goat">Goat (Kambing)</option>
                      <option value="Carabao">Carabao (Kalabaw)</option>
                      <option value="Other">Other Species (Specify)</option>
                    </select>
                    <div id="assist_species_other_wrap" style="display:none;margin-top:.35rem;">
                      <input type="text" name="animal_species_other" id="assist_species_other" placeholder="Specify animal species" class="form-input">
                    </div>
                  </div>

                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Number of Heads *</label>
                    <input type="number" name="head_count" id="assist_head_count" min="1" value="1" required class="form-input" oninput="updateMixedSexLimits()">
                  </div>

                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Sex *</label>
                    <select name="sex" id="assist_sex" class="form-input" onchange="handleAssistSexChange(this.value)" required>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                      <option value="Mixed">Mixed (Male &amp; Female)</option>
                    </select>
                  </div>

                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Age of Animals *</label>
                    <input type="text" name="animal_age" required class="form-input" placeholder="e.g. 6 months, 2 years, Adult" value="1 year">
                  </div>
                </div>

                <!-- Sub-fields for Mixed Sex -->
                <div id="mixedSexRow" style="display:none;background:#f8fafc;padding:.75rem;border-radius:8px;border:1px dashed #cbd5e1;margin-top:.85rem;">
                  <strong style="font-size:.76rem;color:#0f172a;display:block;margin-bottom:.4rem;"><i class="bi bi-gender-ambiguous"></i> Specify Head Count for Mixed-Sex Livestock:</strong>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div>
                      <label style="font-size:.74rem;color:#475569;font-weight:600;">Number of Males</label>
                      <input type="number" name="male_count" id="assist_male_count" min="0" value="0" class="form-input">
                    </div>
                    <div>
                      <label style="font-size:.74rem;color:#475569;font-weight:600;">Number of Females</label>
                      <input type="number" name="female_count" id="assist_female_count" min="0" value="0" class="form-input">
                    </div>
                  </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.85rem;margin-top:.85rem;">
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Purpose *</label>
                    <select name="purpose" id="assist_purpose" class="form-input" onchange="handleAssistPurposeChange(this.value)" required>
                      <option value="Slaughter">Slaughter (Pakatay / Meat)</option>
                      <option value="Commercial">Commercial (Trading / Sale)</option>
                      <option value="Personal">Personal (Backyard / Breeding / Consumption)</option>
                      <option value="Other">Other (Specify)</option>
                    </select>
                    <div id="assist_purpose_other_wrap" style="display:none;margin-top:.35rem;">
                      <input type="text" name="purpose_other" id="assist_purpose_other" placeholder="Specify purpose" class="form-input">
                    </div>
                  </div>

                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Estimated Total Value (PHP) *</label>
                    <input type="number" name="estimated_value" step="0.01" min="0" required class="form-input" placeholder="e.g. 25000.00" value="15000.00">
                  </div>
                </div>

                <div style="margin-top:.85rem;">
                  <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Animal Description &amp; Identifying Marks</label>
                  <textarea name="animal_description" rows="2" class="form-input" placeholder="Specify color, physical markings, ear tag numbers, breed, or distinct traits (e.g. Black with white snout, Ear Tag #042)..."></textarea>
                </div>
              </div>

              <!-- ================= SECTION D. ORIGIN AND DESTINATION ================= -->
              <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:1.15rem;margin-bottom:1.25rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;">
                  <h3 style="font-size:.96rem;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:.45rem;">
                    <span style="width:24px;height:24px;border-radius:50%;background:#059669;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;">D</span>
                    Origin and Destination
                  </h3>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Origin Barangay *</label>
                    <input type="text" name="origin_barangay" required class="form-input" value="<?= htmlspecialchars($fBarangay ?: 'Poblacion') ?>" placeholder="Barangay where animals are located">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Origin Municipality *</label>
                    <select name="origin_municipality" class="form-input" required>
                      <?php foreach(['Ragay','Sipocot','Lupi','Del Gallego','Cabusao'] as $lgu): ?>
                        <option value="<?= $lgu ?>" <?= (strcasecmp($farmerMuni, $lgu) === 0 ? 'selected' : '') ?>><?= $lgu ?>, Camarines Sur</option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <!-- Destination sub-fields (Visible when Slaughter Assistance is selected) -->
                <div id="wrapDestinationSection" style="display:none;margin-top:.85rem;padding-top:.85rem;border-top:1px dashed #cbd5e1;">
                  <strong style="font-size:.8rem;color:#0f172a;display:block;margin-bottom:.5rem;"><i class="bi bi-geo-alt-fill" style="color:#db2777;"></i> Shipment / Slaughter Destination:</strong>
                  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.85rem;">
                    <div>
                      <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Destination Barangay</label>
                      <input type="text" name="dest_barangay" class="form-input" placeholder="e.g. Poblacion, San Rafael">
                    </div>
                    <div>
                      <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Destination Municipality</label>
                      <select name="dest_municipality" class="form-input">
                        <?php foreach(['Ragay','Sipocot','Lupi','Del Gallego','Cabusao'] as $lgu): ?>
                          <option value="<?= $lgu ?>"><?= $lgu ?>, Camarines Sur</option>
                        <?php endforeach; ?>
                        <option value="Other">Other City/Province</option>
                      </select>
                    </div>
                  </div>
                  <div style="margin-top:.6rem;">
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Complete Destination Address / Facility</label>
                    <input type="text" name="dest_address" class="form-input" placeholder="e.g. Municipal Slaughterhouse, Meat Section Public Market, Registered Meat Shop...">
                  </div>
                </div>
              </div>

              <!-- ================= SECTION E. TRANSPORTATION INFORMATION ================= -->
              <div id="wrapTransportSection" style="display:none;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.15rem;margin-bottom:1.25rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;flex-wrap:wrap;gap:.4rem;">
                  <h3 style="font-size:.96rem;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:.45rem;">
                    <span style="width:24px;height:24px;border-radius:50%;background:#db2777;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;">E</span>
                    Transportation Information (COI Preparation)
                  </h3>
                  <small style="color:#64748b;font-size:.76rem;">Required for Certificate of Inspection &amp; Shipping Permit</small>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.85rem;">
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Means of Transport</label>
                    <select name="transport_means" id="assist_trans_means" class="form-input" onchange="handleAssistTransOther(this.value)">
                      <option value="Forward Truck">Forward Truck</option>
                      <option value="Closed Truck">Closed Truck / Van</option>
                      <option value="Pickup / Multicab">Pickup / Multicab</option>
                      <option value="Livestock Hauler">Livestock Hauler</option>
                      <option value="Other">Other (Specify)</option>
                    </select>
                    <div id="assist_trans_other_wrap" style="display:none;margin-top:.35rem;">
                      <input type="text" name="transport_means_other" placeholder="Specify transport means" class="form-input">
                    </div>
                  </div>

                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Vehicle Plate Number</label>
                    <input type="text" name="plate_number" placeholder="e.g. NAK 1234 or MV File #" class="form-input">
                  </div>

                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Proposed Date of Shipment / Slaughter</label>
                    <input type="date" name="proposed_shipment_date" value="<?= date('Y-m-d') ?>" class="form-input">
                  </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.85rem;margin-top:.85rem;">
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Name of Shipper / Carrier</label>
                    <input type="text" name="shipper_name" value="<?= htmlspecialchars(trim(($profileData['firstName']??'').' '.($profileData['lastName']??''))) ?>" class="form-input" placeholder="Shipper full name">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Shipper's Address</label>
                    <input type="text" name="shipper_address" value="<?= htmlspecialchars($profileData['address'] ?: ($primaryFarm['address'] ?? ($farmerMuni.', Camarines Sur'))) ?>" class="form-input" placeholder="Shipper address">
                  </div>
                  <div>
                    <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Shipper's Contact Number</label>
                    <input type="text" name="shipper_contact" value="<?= htmlspecialchars($profileData['mobile'] ?? '') ?>" class="form-input" placeholder="Contact number">
                  </div>
                </div>

                <div style="margin-top:.85rem;">
                  <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Additional Transportation Details</label>
                  <input type="text" name="transport_details" class="form-input" placeholder="e.g. Route through Sipocot bypass, live animal transport precautions, crate secured...">
                </div>
              </div>

              <!-- ================= SECTION F. TREATMENT / MEDICATION HISTORY ================= -->
              <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:1.15rem;margin-bottom:1.25rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;flex-wrap:wrap;gap:.4rem;">
                  <h3 style="font-size:.96rem;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:.45rem;">
                    <span style="width:24px;height:24px;border-radius:50%;background:#059669;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;">F</span>
                    Treatment / Medication History (For Veterinary Health Certificate)
                  </h3>
                  <small style="color:#64748b;font-size:.76rem;">Subject to municipal veterinarian clinical review</small>
                </div>

                <div style="margin-bottom:.75rem;">
                  <label class="form-label" style="font-size:.82rem;font-weight:700;color:#1e293b;display:block;margin-bottom:.35rem;">
                    Has the livestock received any treatment, vaccine, or medication? *
                  </label>
                  <div style="display:flex;gap:1.5rem;align-items:center;">
                    <label style="display:inline-flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.86rem;font-weight:600;">
                      <input type="radio" name="has_treatment" value="No" checked onchange="toggleTreatmentMedHistory('No')">
                      <span>No (No treatment or medication declared)</span>
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.86rem;font-weight:600;color:#059669;">
                      <input type="radio" name="has_treatment" value="Yes" onchange="toggleTreatmentMedHistory('Yes')">
                      <span>Yes (Has prior treatments / medication)</span>
                    </label>
                  </div>
                </div>

                <!-- Dynamic Medication Repeater -->
                <div id="wrapMedicationRepeater" style="display:none;background:#fff;border:1px solid #cbd5e1;border-radius:10px;padding:.85rem;margin-top:.75rem;">
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;">
                    <strong style="font-size:.8rem;color:#0f172a;"><i class="bi bi-capsule"></i> Medication &amp; Treatment Records</strong>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="addMedicationRow()" style="font-size:.74rem;padding:.2rem .6rem;">
                      <i class="bi bi-plus-circle"></i> Add Medication Row
                    </button>
                  </div>

                  <table style="width:100%;border-collapse:collapse;font-size:.78rem;" id="tblMedicationRows">
                    <thead>
                      <tr style="background:#f1f5f9;text-align:left;color:#475569;">
                        <th style="padding:.4rem;border:1px solid #cbd5e1;">Date of Treatment</th>
                        <th style="padding:.4rem;border:1px solid #cbd5e1;">Medicine Administered</th>
                        <th style="padding:.4rem;border:1px solid #cbd5e1;">Route of Administration</th>
                        <th style="padding:.4rem;border:1px solid #cbd5e1;">Withdrawal Period</th>
                        <th style="padding:.4rem;border:1px solid #cbd5e1;width:40px;text-align:center;">Action</th>
                      </tr>
                    </thead>
                    <tbody id="medicationRowsBody">
                      <tr>
                        <td style="padding:.4rem;border:1px solid #cbd5e1;"><input type="date" class="form-input med-date" style="padding:.25rem;font-size:.76rem;" value="<?= date('Y-m-d') ?>"></td>
                        <td style="padding:.4rem;border:1px solid #cbd5e1;"><input type="text" class="form-input med-name" placeholder="e.g. Iron Dextran, Penicillin" style="padding:.25rem;font-size:.76rem;"></td>
                        <td style="padding:.4rem;border:1px solid #cbd5e1;">
                          <select class="form-input med-route" style="padding:.25rem;font-size:.76rem;">
                            <option value="Intramuscular (IM)">Intramuscular (IM)</option>
                            <option value="Subcutaneous (SC)">Subcutaneous (SC)</option>
                            <option value="Oral">Oral</option>
                            <option value="Topical">Topical</option>
                            <option value="Intravenous (IV)">Intravenous (IV)</option>
                          </select>
                        </td>
                        <td style="padding:.4rem;border:1px solid #cbd5e1;"><input type="text" class="form-input med-withdrawal" placeholder="e.g. 14 days, None" style="padding:.25rem;font-size:.76rem;"></td>
                        <td style="padding:.4rem;border:1px solid #cbd5e1;text-align:center;">
                          <button type="button" class="btn btn-sm btn-danger" onclick="removeMedicationRow(this)" style="padding:.15rem .35rem;font-size:.7rem;"><i class="bi bi-trash"></i></button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <input type="hidden" name="treatment_history" id="assist_treatment_history_json" value="">
                </div>

                <div style="margin-top:.6rem;font-size:.74rem;color:#64748b;line-height:1.4;">
                  <i class="bi bi-info-circle-fill" style="color:#0284c7;"></i> <i>Official Notice: Answers provided will be evaluated by the authorized LGU Veterinarian during ante-mortem inspection. Declaration of treatment does not automatically disqualify animals; it ensures compliance with proper withdrawal periods.</i>
                </div>
              </div>

              <!-- ================= SECTION G. SUPPORTING DOCUMENTS ================= -->
              <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.15rem;margin-bottom:1.25rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;">
                  <h3 style="font-size:.96rem;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:.45rem;">
                    <span style="width:24px;height:24px;border-radius:50%;background:#059669;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;">G</span>
                    Supporting Documents &amp; Attachments
                  </h3>
                  <small style="color:#64748b;font-size:.76rem;">Accepted: JPG, JPEG, PNG, PDF</small>
                </div>

                <div>
                  <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Upload File (Animal photographs, Farm photographs, Ear tag photos, or Supporting documents)</label>
                  <input type="file" name="supporting_doc" accept=".jpg,.jpeg,.png,.pdf,.webp" class="form-input" style="padding:.4rem;font-size:.8rem;">
                  <small style="color:#64748b;font-size:.72rem;display:block;margin-top:.25rem;">Optional but highly recommended to expedite official approval and certificate preparation.</small>
                </div>
              </div>

              <!-- ================= SECTION H. REQUEST DESCRIPTION & DECLARATION ================= -->
              <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:1.15rem;margin-bottom:1.25rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;">
                  <h3 style="font-size:.96rem;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:.45rem;">
                    <span style="width:24px;height:24px;border-radius:50%;background:#059669;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;">H</span>
                    Request Description &amp; Certification
                  </h3>
                </div>

                <div style="margin-bottom:.85rem;">
                  <label class="form-label" style="font-size:.78rem;font-weight:700;color:#334155;margin-bottom:.25rem;display:block;">Describe your request or concern *</label>
                  <textarea name="request_description" rows="3" required class="form-input" placeholder="Explain why you need veterinary assistance or slaughter clearance (e.g. Clinical diagnosis for coughing swine, vaccination booster, slaughter clearance for local fiesta occasion, transport to Sipocot abattoir)..."></textarea>
                </div>

                <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:.75rem;margin-bottom:1.25rem;">
                  <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer;font-size:.82rem;font-weight:700;color:#065f46;">
                    <input type="checkbox" name="certified_accurate" value="1" required checked style="margin-top:2px;">
                    <span>I certify that the information I have provided is true and accurate to the best of my knowledge.</span>
                  </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="btnSubmitUnifiedAssist" class="btn btn-primary" style="width:100%;padding:.75rem;font-size:.92rem;font-weight:800;display:flex;align-items:center;justify-content:center;gap:.5rem;box-shadow:0 4px 12px rgba(5,150,105,0.25);">
                  <i class="bi bi-send-check-fill"></i> Review &amp; Submit Assistance Request
                </button>
              </div>
            </form>
          </div>

          <!-- PANE 2: MY SUBMITTED REQUESTS -->
          <div id="assistPaneHistory" style="display:none;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
              <h3 style="font-size:1rem;font-weight:800;color:#0f172a;margin:0;">
                <i class="bi bi-clock-history" style="color:#059669;"></i> Submitted Requests &amp; Certificate Tracking
              </h3>
              <span style="font-size:.78rem;color:#64748b;">Live status tracking &bull; Official VHC &amp; COI Downloads</span>
            </div>

            <?php if(empty($assistanceRequests)): ?>
              <div style="text-align:center;padding:3rem 1rem;background:#f8fafc;border:2px dashed #cbd5e1;border-radius:14px;">
                <div style="width:54px;height:54px;background:#e2e8f0;border-radius:50%;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto .75rem;">
                  <i class="bi bi-inbox-fill"></i>
                </div>
                <strong style="display:block;font-size:.92rem;color:#1e293b;">No Assistance Requests Submitted Yet</strong>
                <p style="font-size:.8rem;color:#64748b;margin:.35rem auto 1rem;max-width:380px;">
                  Fill out the unified assistance form to request veterinary consultation, treatment, or slaughter clearance.
                </p>
                <button type="button" class="btn btn-primary" onclick="switchAssistanceTab('new')" style="padding:.45rem 1rem;font-size:.82rem;">
                  <i class="bi bi-plus-circle"></i> Create Assistance Request
                </button>
              </div>
            <?php else: ?>
              <div style="display:flex;flex-direction:column;gap:.85rem;">
                <?php foreach($assistanceRequests as $ar): 
                  $isVet = ($ar['assistance_type'] === 'Veterinary Assistance');
                  $st = $ar['status'];
                  $stBadgeClr = '#d97706';
                  if (in_array($st, ['Approved', 'Certificate Issued', 'Completed'])) $stBadgeClr = '#059669';
                  elseif ($st === 'Rejected') $stBadgeClr = '#dc2626';
                  elseif (in_array($st, ['Under Review', 'For Inspection', 'Certificate Processing'])) $stBadgeClr = '#0284c7';
                ?>
                <div style="background:#fff;border:1px solid #e2e8f0;border-left:5px solid <?= $isVet ? '#059669' : '#db2777' ?>;border-radius:12px;padding:1rem;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:.5rem;">
                    <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;">
                      <span style="font-weight:800;font-size:.92rem;color:#0f172a;"><i class="bi bi-ticket-perforated-fill" style="color:#059669;"></i> <?= htmlspecialchars($ar['reference_no']) ?></span>
                      <span style="background:<?= $isVet ? '#ecfdf5' : '#fdf2f8' ?>;color:<?= $isVet ? '#065f46' : '#9d174d' ?>;font-size:.72rem;font-weight:800;padding:.15rem .55rem;border-radius:99px;border:1px solid <?= $isVet ? '#a7f3d0' : '#fbcfe8' ?>;">
                        <i class="bi <?= $isVet ? 'bi-bandaid-fill' : 'bi-receipt-cutoff' ?>"></i> <?= htmlspecialchars($ar['assistance_type']) ?>
                      </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:.4rem;">
                      <span style="background:<?= $stBadgeClr ?>15;color:<?= $stBadgeClr ?>;font-size:.75rem;font-weight:800;padding:.2rem .65rem;border-radius:99px;border:1px solid <?= $stBadgeClr ?>40;">
                        <i class="bi bi-circle-fill" style="font-size:.5rem;vertical-align:middle;"></i> <?= htmlspecialchars($ar['status']) ?>
                      </span>
                    </div>
                  </div>

                  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.6rem;font-size:.78rem;color:#475569;margin-bottom:.65rem;background:#f8fafc;padding:.65rem .85rem;border-radius:8px;">
                    <div><b style="color:#1e293b;">Livestock:</b> <?= (int)$ar['head_count'] ?> heads &bull; <?= htmlspecialchars($ar['animal_species']) ?> (<?= htmlspecialchars($ar['sex']) ?>)</div>
                    <div><b style="color:#1e293b;">Purpose:</b> <?= htmlspecialchars($ar['purpose']) ?></div>
                    <div><b style="color:#1e293b;">Origin / LGU:</b> <?= htmlspecialchars($ar['origin_barangay'] ? $ar['origin_barangay'].', ' : '') ?><?= htmlspecialchars($ar['municipality']) ?></div>
                    <div><b style="color:#1e293b;">Submitted:</b> <?= date('M d, Y h:i A', strtotime($ar['created_at'])) ?></div>
                  </div>

                  <p style="font-size:.8rem;color:#334155;margin:0 0 .65rem;line-height:1.4;">
                    <b style="color:#0f172a;">Description:</b> <?= nl2br(htmlspecialchars($ar['request_description'])) ?>
                  </p>

                  <?php if(!empty($ar['official_remarks'])): ?>
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.5rem .75rem;font-size:.76rem;color:#166534;margin-bottom:.65rem;">
                      <strong><i class="bi bi-chat-left-text-fill"></i> Official DA Remarks:</strong> <?= htmlspecialchars($ar['official_remarks']) ?>
                    </div>
                  <?php endif; ?>

                  <!-- Action Buttons -->
                  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;padding-top:.5rem;border-top:1px solid #f1f5f9;">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="viewFarmerAssistanceDetails(<?= (int)$ar['id'] ?>)" style="padding:.3rem .75rem;font-size:.78rem;">
                      <i class="bi bi-eye-fill"></i> View Request Details
                    </button>

                    <div style="display:flex;gap:.4rem;align-items:center;">
                      <?php if((int)($ar['has_vhc'] ?? 0) > 0): ?>
                        <button type="button" class="btn btn-sm btn-pdf-download" onclick="openCertificateModal(<?= (int)$ar['id'] ?>, 'VHC')" style="padding:.3rem .75rem;font-size:.78rem;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;font-weight:700;">
                          <i class="bi bi-file-earmark-pdf-fill"></i> Download VHC
                        </button>
                      <?php endif; ?>

                      <?php if((int)($ar['has_coi'] ?? 0) > 0): ?>
                        <button type="button" class="btn btn-sm btn-pdf-download" onclick="openCertificateModal(<?= (int)$ar['id'] ?>, 'COI')" style="padding:.3rem .75rem;font-size:.78rem;background:#fdf2f8;color:#9d174d;border:1px solid #fbcfe8;font-weight:700;">
                          <i class="bi bi-file-earmark-pdf-fill"></i> Download COI
                        </button>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ===== PCIC LIVESTOCK INSURANCE ASSISTANCE ===== -->
      <div id="pg-insurance" class="page">
        <div class="card" style="padding:1.25rem;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem;">
            <div>
              <h2 style="font-size:1.25rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:.5rem;margin:0;">
                <i class="bi bi-shield-check" style="color:#d97706;"></i> PCIC Livestock Insurance Assistance
              </h2>
              <p style="margin:.25rem 0 0;font-size:.82rem;color:var(--sub);">
                Philippine Crop Insurance Corporation (PCIC) livestock mortality protection, subsidized policy referrals, and official claim filing.
              </p>
            </div>
            <span style="background:#fef3c7;color:#92400e;padding:.35rem .85rem;border-radius:9999px;font-size:.78rem;font-weight:700;border:1px solid #fde68a;">
              <i class="bi bi-award-fill"></i> Government Subsidized Coverage
            </span>
          </div>

          <!-- PCIC Overview Banner -->
          <div style="background:linear-gradient(135deg,#064e3b,#065f46);color:#fff;border-radius:14px;padding:1.15rem;margin-bottom:1.25rem;">
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem;">
              <div style="font-size:1.6rem;"><i class="bi bi-shield-check"></i></div>
              <div>
                <h3 style="font-size:1.05rem;font-weight:800;margin:0;">Free PCIC Protection for Registered Livestock</h3>
                <p style="font-size:.8rem;opacity:.9;margin:.15rem 0 0;">Under DA Region V agricultural safety nets, smallholder raisers in Camarines Sur 1st District are eligible for subsidized animal mortality insurance against accidental death, disease outbreaks, and typhoons.</p>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;margin-top:.75rem;padding-top:.75rem;border-top:1px solid rgba(255,255,255,.15);font-size:.78rem;">
              <div><strong><i class="bi bi-check-circle-fill"></i> Swine (Fatteners &amp; Breeders):</strong> Up to PHP 10,000 / head</div>
              <div><strong><i class="bi bi-check-circle-fill"></i> Cattle / Carabao:</strong> Up to PHP 20,000 / head</div>
              <div><strong><i class="bi bi-check-circle-fill"></i> Goats / Small Ruminants:</strong> Up to PHP 3,500 / head</div>
            </div>
          </div>

          <!-- Requirements Checklist & Forms Grid -->
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.25rem;">
            <!-- Left: Filing Forms -->
            <div>
              <div style="display:flex;gap:.5rem;margin-bottom:1rem;border-bottom:1px solid #e2e8f0;padding-bottom:.5rem;">
                <button type="button" class="btn btn-primary" id="btnTabInsApply" onclick="switchInsuranceTab('apply')" style="padding:.4rem .85rem;font-size:.8rem;">
                  <i class="bi bi-file-earmark-plus"></i> Apply / Referral
                </button>
                <button type="button" class="btn btn-secondary" id="btnTabInsDeath" onclick="switchInsuranceTab('death')" style="padding:.4rem .85rem;font-size:.8rem;">
                  <i class="bi bi-exclamation-octagon-fill" style="color:#ef4444;"></i> Report Livestock Death / Claim
                </button>
              </div>

              <!-- Form A: Application / Referral -->
              <div id="insFormApplyWrap" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:1.15rem;">
                <h4 style="font-size:.9rem;font-weight:700;margin-bottom:.75rem;color:#0f172a;">Request PCIC Insurance Endorsement</h4>
                <form id="insuranceApplyForm" onsubmit="submitInsuranceForm(event, 'Insurance Application')" enctype="multipart/form-data">
                  <input type="hidden" name="insurance_type" value="Insurance Application">
                  <div style="margin-bottom:.75rem;">
                    <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Select Farm *</label>
                    <select name="farm_id" class="form-input" required style="width:100%;padding:.48rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.82rem;">
                      <?php foreach($farmsData as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
                    <div>
                      <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Livestock Species *</label>
                      <select name="livestock_type" class="form-input" required style="width:100%;padding:.48rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.82rem;">
                        <option value="Swine">Swine / Pigs</option>
                        <option value="Cattle">Cattle / Cows</option>
                        <option value="Carabao">Carabao</option>
                        <option value="Goat">Goats / Sheep</option>
                        <option value="Poultry">Poultry</option>
                      </select>
                    </div>
                    <div>
                      <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Number of Heads *</label>
                      <input type="number" name="head_count" value="1" min="1" required class="form-input" style="width:100%;padding:.48rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.82rem;">
                    </div>
                  </div>
                  <div style="margin-bottom:.75rem;">
                    <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Upload Supporting Requirements (PDF / Image)</label>
                    <input type="file" name="document_file" accept=".pdf,image/*" class="form-input" style="width:100%;padding:.4rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.78rem;">
                    <small style="font-size:.72rem;color:#64748b;display:block;margin-top:.2rem;">Attach: Accomplished PCIC Form, Barangay Certification, or Ear Tag Registry</small>
                  </div>
                  <button type="submit" class="btn btn-primary" style="width:100%;padding:.6rem;font-size:.85rem;margin-top:.5rem;">
                    <i class="bi bi-send-fill"></i> Submit PCIC Referral Request
                  </button>
                </form>
              </div>

              <!-- Form B: Death / Mortality Claim -->
              <div id="insFormDeathWrap" style="display:none;background:#fef2f2;border:1px solid #fecaca;border-radius:14px;padding:1.15rem;">
                <h4 style="font-size:.9rem;font-weight:700;margin-bottom:.75rem;color:#991b1b;display:flex;align-items:center;gap:.4rem;">
                  <i class="bi bi-exclamation-triangle-fill"></i> File Livestock Mortality Claim
                </h4>
                <form id="insuranceDeathForm" onsubmit="submitInsuranceForm(event, 'Death & Mortality Claim')" enctype="multipart/form-data">
                  <input type="hidden" name="insurance_type" value="Death & Mortality Claim">
                  <div style="margin-bottom:.75rem;">
                    <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Select Farm *</label>
                    <select name="farm_id" class="form-input" required style="width:100%;padding:.48rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.82rem;">
                      <?php foreach($farmsData as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
                    <div>
                      <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Species *</label>
                      <select name="livestock_type" class="form-input" required style="width:100%;padding:.48rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.82rem;">
                        <option value="Swine">Swine / Pigs</option>
                        <option value="Cattle">Cattle / Cows</option>
                        <option value="Carabao">Carabao</option>
                        <option value="Goat">Goats / Sheep</option>
                      </select>
                    </div>
                    <div>
                      <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Dead Heads Count *</label>
                      <input type="number" name="head_count" value="1" min="1" required class="form-input" style="width:100%;padding:.48rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.82rem;">
                    </div>
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
                    <div>
                      <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Cause of Death *</label>
                      <input type="text" name="cause_of_death" required placeholder="Disease, trauma, heat stroke..." class="form-input" style="width:100%;padding:.48rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.82rem;">
                    </div>
                    <div>
                      <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Date of Death *</label>
                      <input type="date" name="date_of_incident" value="<?= date('Y-m-d') ?>" required class="form-input" style="width:100%;padding:.48rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.82rem;">
                    </div>
                  </div>
                  <div style="margin-bottom:.75rem;">
                    <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Estimated Loss Amount (PHP )</label>
                    <input type="number" step="0.01" name="estimated_loss" placeholder="e.g. 15000" class="form-input" style="width:100%;padding:.48rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.82rem;">
                  </div>
                  <div style="margin-bottom:.75rem;">
                    <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Photo Evidence of Deceased Animal *</label>
                    <input type="file" name="photo_file" accept="image/*" required class="form-input" style="width:100%;padding:.4rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.78rem;">
                  </div>
                  <button type="submit" class="btn" style="background:#dc2626;color:#fff;width:100%;padding:.6rem;font-size:.85rem;border:none;border-radius:8px;font-weight:700;">
                    <i class="bi bi-file-earmark-medical-fill"></i> Submit Mortality Claim
                  </button>
                </form>
              </div>
            </div>

            <!-- Right: Application Requirements & Claim Tracking -->
            <div>
              <!-- Requirements Checklist Card -->
              <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.15rem;margin-bottom:1.25rem;">
                <h4 style="font-size:.88rem;font-weight:700;margin-bottom:.65rem;color:#0f172a;display:flex;align-items:center;gap:.4rem;">
                  <i class="bi bi-card-checklist" style="color:#10b981;"></i> PCIC Application Requirements
                </h4>
                <ul style="margin:0;padding-left:1.2rem;font-size:.78rem;color:#475569;line-height:1.6;">
                  <li><strong>Duly Accomplished PCIC Form:</strong> Available from Municipal Agriculture Office.</li>
                  <li><strong>AgriTrace+ Verified Farm ID:</strong> Proof of registration in Camarines Sur District I.</li>
                  <li><strong>Livestock Tag ID &amp; Health Clearance:</strong> Vaccination / Deworming card verified by DA.</li>
                  <li><strong>Valid Government-issued ID:</strong> RSBSA number or national/voter's ID.</li>
                  <li><strong>Notice of Death (Within 48h):</strong> Required for mortality claims to enable post-mortem verification.</li>
                </ul>
              </div>

              <!-- Referral Tracking Table -->
              <h4 style="font-size:.9rem;font-weight:700;margin-bottom:.75rem;color:#0f172a;display:flex;align-items:center;justify-content:space-between;">
                <span><i class="bi bi-clock-history"></i> Track Referrals &amp; Claims</span>
                <span style="font-size:.75rem;font-weight:600;color:var(--sub);"><?= count($insuranceRecords) ?> Recorded</span>
              </h4>

              <?php if(empty($insuranceRecords)): ?>
                <div style="text-align:center;padding:2rem 1rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:14px;">
                  <p style="font-size:.8rem;color:#64748b;margin:0;">No insurance referrals or claims recorded yet.</p>
                </div>
              <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:.65rem;max-height:380px;overflow-y:auto;">
                  <?php foreach($insuranceRecords as $ir): 
                    $pClr = match($ir['pcic_status']) {
                      'Claim Approved', 'Disbursed' => '#10b981',
                      'Endorsed to PCIC', 'Under PCIC Review' => '#3b82f6',
                      'Rejected' => '#ef4444',
                      default => '#d97706'
                    };
                  ?>
                  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:.75rem;font-size:.78rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.25rem;">
                      <strong><?= htmlspecialchars($ir['reference_no']) ?></strong>
                      <span style="background:<?= $pClr ?>15;color:<?= $pClr ?>;padding:.15rem .5rem;border-radius:99px;font-size:.68rem;font-weight:700;border:1px solid <?= $pClr ?>40;">
                        <?= htmlspecialchars($ir['pcic_status']) ?>
                      </span>
                    </div>
                    <div style="color:#64748b;font-size:.74rem;">
                      <span><?= htmlspecialchars($ir['request_type']) ?></span> &bull; 
                      <span><?= (int)$ir['head_count'] ?> <?= htmlspecialchars($ir['livestock_type']) ?></span>
                      <?php if((float)$ir['estimated_loss'] > 0): ?>
                        &bull; <span>PHP <?= number_format((float)$ir['estimated_loss'], 2) ?></span>
                      <?php endif; ?>
                    </div>
                    <?php if(!empty($ir['officer_notes'])): ?>
                      <div style="background:#f8fafc;border-radius:6px;padding:.3rem .5rem;margin-top:.35rem;color:#334155;font-size:.72rem;">
                        <strong>DA Update:</strong> <?= htmlspecialchars($ir['officer_notes']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ===== SLAUGHTER ACTIVITY DECLARATION (Consolidated to Unified Request Form) ===== -->
      <div id="pg-slaughter" class="page">
        <div class="card" style="padding:1.5rem;">
          <div style="background:linear-gradient(135deg,#fdf2f8,#fff);border:1.5px solid #fbcfe8;border-radius:14px;padding:1.75rem;text-align:center;margin-bottom:1.5rem;">
            <div style="width:58px;height:58px;border-radius:50%;background:#fce7f3;color:#db2777;display:flex;align-items:center;justify-content:center;font-size:1.75rem;margin:0 auto 1rem;box-shadow:0 2px 8px rgba(219,39,119,0.15);">
              <i class="bi bi-receipt-cutoff"></i>
            </div>
            <h2 style="font-size:1.35rem;font-weight:800;color:#0f172a;margin-bottom:.5rem;">
              Unified Farmer Assistance Request Form
            </h2>
            <p style="font-size:.88rem;color:#475569;max-width:540px;margin:0 auto 1.25rem;line-height:1.5;">
              Slaughter Assistance and Veterinary Assistance have been merged into <b>ONE Unified Request Form</b>. Declarations, transportation details, and Certificate of Inspection (COI) requests are now processed through the unified module.
            </p>
            <button type="button" class="btn btn-primary" onclick="navTo('vet-request'); setAssistanceType('Slaughter Assistance');" style="background:#db2777;border-color:#db2777;padding:.65rem 1.4rem;font-size:.9rem;font-weight:700;display:inline-flex;align-items:center;gap:.5rem;box-shadow:0 4px 12px rgba(219,39,119,0.25);">
              <i class="bi bi-pencil-square"></i> Open Unified Request Form for Slaughter Assistance
            </button>
          </div>

          <!-- Existing slaughter declaration records -->
          <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.15rem;">
            <h3 style="font-size:.95rem;font-weight:700;margin-bottom:.85rem;color:#0f172a;display:flex;align-items:center;gap:.45rem;">
              <i class="bi bi-clock-history" style="color:#db2777;"></i> Prior Slaughter Declarations History
            </h3>
            <?php if(empty($slaughterRecords)): ?>
              <div style="text-align:center;padding:2rem 1rem;color:#64748b;font-size:.82rem;">
                No prior individual slaughter declarations. All new requests are filed via the <a href="javascript:void(0)" onclick="navTo('vet-request'); setAssistanceType('Slaughter Assistance');" style="color:#db2777;font-weight:700;">Unified Assistance Form</a>.
              </div>
            <?php else: ?>
              <div style="display:flex;flex-direction:column;gap:.75rem;">
                <?php foreach($slaughterRecords as $sr): 
                  $sClr = $sr['status'] === 'Approved' || $sr['status'] === 'Verified' ? '#10b981' : ($sr['status'] === 'Flagged/Investigating' ? '#ef4444' : '#d97706');
                ?>
                <div style="background:#fff;border:1px solid #e2e8f0;border-left:4px solid <?= $sClr ?>;border-radius:10px;padding:.85rem;">
                  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.4rem;">
                    <strong><?= htmlspecialchars($sr['livestock_type']) ?> &bull; Tag: <?= htmlspecialchars($sr['animal_tag_id']) ?></strong>
                    <span style="background:<?= $sClr ?>15;color:<?= $sClr ?>;font-size:.7rem;font-weight:700;padding:.15rem .5rem;border-radius:99px;border:1px solid <?= $sClr ?>40;"><?= htmlspecialchars($sr['status']) ?></span>
                  </div>
                  <div style="font-size:.76rem;color:#64748b;margin:.3rem 0;">
                    Ref: <b><?= htmlspecialchars($sr['declaration_no']) ?></b> &bull; Date: <?= date('M d, Y', strtotime($sr['slaughter_date'])) ?> &bull; Location: <?= htmlspecialchars($sr['location']) ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ===== DIGITAL CERTIFICATE MANAGEMENT (UPGRADED E-CERTIFICATE) ===== -->
      <div id="pg-ecert" class="page">
        <div class="card" style="padding:1.25rem;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem;">
            <div>
              <h2 style="font-size:1.25rem;font-weight:800;color:var(--txt);display:flex;align-items:center;gap:.5rem;margin:0;">
                <i class="bi bi-patch-check-fill" style="color:#10b981;"></i> Digital Certificate Management
              </h2>
              <p style="margin:.25rem 0 0;font-size:.82rem;color:var(--sub);">
                Upload, verify, and monitor official animal ownership certificates, veterinary health clearances, vaccination proofs, shipping permits, and lab results.
              </p>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
              <?php $totalCertCount = count($digitalCertificates) + count($myAssistanceCerts); ?>
              <span style="background:#ecfdf5;color:#065f46;padding:.35rem .75rem;border-radius:9999px;font-size:.78rem;font-weight:700;border:1px solid #a7f3d0;">
                <i class="bi bi-file-earmark-lock"></i> <?= $totalCertCount ?> Document<?= $totalCertCount!==1?'s':'' ?>
              </span>
              <button type="button" class="btn btn-primary" onclick="openModal('uploadCertModal')" style="padding:.45rem .95rem;font-size:.82rem;display:inline-flex;align-items:center;gap:.4rem;">
                <i class="bi bi-upload"></i> Upload Certificate
              </button>
            </div>
          </div>

          <!-- Document Category Filter Pills -->
          <div style="display:flex;gap:.5rem;overflow-x:auto;padding-bottom:.65rem;margin-bottom:1.25rem;border-bottom:1px solid #e2e8f0;">
            <button type="button" class="btn btn-secondary active" onclick="filterCertCards('all', this)" style="padding:.35rem .75rem;font-size:.78rem;white-space:nowrap;border-radius:99px;">
              <i class="bi bi-collection-fill"></i> All Documents (<?= count($digitalCertificates) + count($myAssistanceCerts) ?>)
            </button>
            <button type="button" class="btn btn-secondary" onclick="filterCertCards('VHC', this)" style="padding:.35rem .75rem;font-size:.78rem;white-space:nowrap;border-radius:99px;">
              <i class="bi bi-patch-check-fill" style="color:#059669;"></i> Issued VHC (<?= count(array_filter($myAssistanceCerts, fn($x) => ($x['cert_type'] ?? '') === 'VHC')) ?>)
            </button>
            <button type="button" class="btn btn-secondary" onclick="filterCertCards('COI', this)" style="padding:.35rem .75rem;font-size:.78rem;white-space:nowrap;border-radius:99px;">
              <i class="bi bi-receipt-cutoff" style="color:#db2777;"></i> Issued COI (<?= count(array_filter($myAssistanceCerts, fn($x) => ($x['cert_type'] ?? '') === 'COI')) ?>)
            </button>
            <button type="button" class="btn btn-secondary" onclick="filterCertCards('Ownership Certificate', this)" style="padding:.35rem .75rem;font-size:.78rem;white-space:nowrap;border-radius:99px;">
              <i class="bi bi-file-earmark-person-fill"></i> Ownership Certificates
            </button>
            <button type="button" class="btn btn-secondary" onclick="filterCertCards('Veterinary Health Certificate', this)" style="padding:.35rem .75rem;font-size:.78rem;white-space:nowrap;border-radius:99px;">
              <i class="bi bi-heart-pulse-fill"></i> Veterinary Health
            </button>
            <button type="button" class="btn btn-secondary" onclick="filterCertCards('Vaccination Certificate', this)" style="padding:.35rem .75rem;font-size:.78rem;white-space:nowrap;border-radius:99px;">
              <i class="bi bi-eyedropper"></i> Vaccination Cards
            </button>
            <button type="button" class="btn btn-secondary" onclick="filterCertCards('Shipping Permit', this)" style="padding:.35rem .75rem;font-size:.78rem;white-space:nowrap;border-radius:99px;">
              <i class="bi bi-truck"></i> Shipping Permits
            </button>
            <button type="button" class="btn btn-secondary" onclick="filterCertCards('Laboratory Result', this)" style="padding:.35rem .75rem;font-size:.78rem;white-space:nowrap;border-radius:99px;">
              <i class="bi bi-flask"></i> Laboratory Results
            </button>
            <button type="button" class="btn btn-secondary" onclick="filterCertCards('Insurance Document', this)" style="padding:.35rem .75rem;font-size:.78rem;white-space:nowrap;border-radius:99px;">
              <i class="bi bi-shield-check"></i> Insurance Documents
            </button>
            
          </div>

          <!-- Monitored Certificates Grid -->
          <div id="certCardsGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;margin-bottom:1.5rem;">
            <?php foreach($myAssistanceCerts as $acRow):
              $acType = strtoupper($acRow['cert_type'] ?? 'VHC');
              $isVhcCard = ($acType === 'VHC');
              $acIsExp = !empty($acRow['expiry_date']) && strtotime($acRow['expiry_date']) < strtotime(date('Y-m-d'));
              $acClr = $isVhcCard ? '#059669' : '#db2777';
              $acTitle = $isVhcCard ? 'Veterinary Health Certificate (VHC)' : 'Certificate of Inspection (COI)';
              $acSpecies = $acRow['verified_species'] ?: ($acRow['animal_species'] ?? 'Livestock');
              $acHeads = $acRow['verified_heads'] ?: ($acRow['head_count'] ?? 1);
            ?>
            <div class="cert-item-card" data-cert-type="<?= htmlspecialchars($acType) ?>" style="background:#fff;border:1.5px solid <?= $acClr ?>40;border-radius:12px;padding:1rem;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 2px 6px rgba(0,0,0,.04);">
              <div>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;">
                  <div style="width:36px;height:36px;border-radius:8px;background:<?= $acClr ?>15;color:<?= $acClr ?>;display:flex;align-items:center;justify-content:center;font-size:1.15rem;">
                    <i class="bi <?= $isVhcCard ? 'bi-heart-pulse-fill' : 'bi-receipt-cutoff' ?>"></i>
                  </div>
                  <div style="display:flex;gap:4px;align-items:center;">
                    <span style="background:<?= $acClr ?>15;color:<?= $acClr ?>;font-size:.66rem;font-weight:800;padding:.15rem .5rem;border-radius:99px;border:1px solid <?= $acClr ?>40;">
                      OFFICIAL <?= htmlspecialchars($acType) ?>
                    </span>
                    <span style="background:<?= $acIsExp ? '#fee2e2' : '#ecfdf5' ?>;color:<?= $acIsExp ? '#b91c1c' : '#065f46' ?>;font-size:.66rem;font-weight:700;padding:.15rem .45rem;border-radius:99px;">
                      <?= $acIsExp ? 'EXPIRED' : 'ISSUED' ?>
                    </span>
                  </div>
                </div>
                <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 .3rem;line-height:1.3;">
                  <?= htmlspecialchars($acTitle) ?>
                </h4>
                <div style="font-size:.74rem;color:#475569;margin-bottom:.5rem;line-height:1.5;">
                  <div><i class="bi bi-upc-scan"></i> <strong><?= htmlspecialchars($acRow['control_number'] ?? '') ?></strong></div>
                  <div><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($acSpecies) ?> &bull; <strong><?= htmlspecialchars((string)$acHeads) ?> head(s)</strong></div>
                  <div><i class="bi bi-person-badge"></i> <?= htmlspecialchars($acRow['vet_name'] ?? 'Municipal Veterinarian') ?></div>
                  <div><i class="bi bi-calendar-check"></i> Issued: <?= !empty($acRow['issue_date']) ? date('M d, Y', strtotime($acRow['issue_date'])) : 'N/A' ?></div>
                </div>
              </div>
              <div style="border-top:1px solid #f1f5f9;padding-top:.65rem;margin-top:.4rem;display:flex;flex-direction:column;gap:.45rem;">
                <div style="font-size:.71rem;color:#64748b;">
                  Valid Until: <strong><?= !empty($acRow['expiry_date']) ? date('M d, Y', strtotime($acRow['expiry_date'])) : 'N/A' ?></strong>
                </div>
                <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                  <button type="button" class="btn btn-secondary" onclick="openFarmerDigitalCert(<?= (int)$acRow['id'] ?>, 'view')" style="flex:1;padding:.3rem .5rem;font-size:.72rem;display:inline-flex;align-items:center;justify-content:center;gap:.25rem;">
                    <i class="bi bi-eye-fill"></i> View
                  </button>
                  <button type="button" class="btn btn-secondary" onclick="openFarmerDigitalCert(<?= (int)$acRow['id'] ?>, 'print')" style="flex:1;padding:.3rem .5rem;font-size:.72rem;display:inline-flex;align-items:center;justify-content:center;gap:.25rem;">
                    <i class="bi bi-printer-fill"></i> Print
                  </button>
                  <button type="button" class="btn btn-primary" onclick="openFarmerDigitalCert(<?= (int)$acRow['id'] ?>, 'pdf')" style="flex:1;padding:.3rem .5rem;font-size:.72rem;background:<?= $acClr ?>;border-color:<?= $acClr ?>;display:inline-flex;align-items:center;justify-content:center;gap:.25rem;">
                    <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                  </button>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($digitalCertificates) && empty($myAssistanceCerts)): ?>
              <div style="grid-column:1/-1;text-align:center;padding:3rem 1rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:14px;">
                <div style="font-size:2.2rem;color:#94a3b8;margin-bottom:.5rem;"><i class="bi bi-folder-fill"></i></div>
                <strong style="display:block;font-size:.95rem;color:#334155;">No Digital Certificates Uploaded Yet</strong>
                <p style="font-size:.8rem;color:#64748b;margin:.25rem 0 1rem;">Upload veterinary health clearances, ownership titles, vaccination records, and shipping permits for permanent digital monitoring.</p>
                <button type="button" class="btn btn-primary" onclick="openModal('uploadCertModal')"><i class="bi bi-upload"></i> Upload First Certificate</button>
              </div>
            <?php else: ?>
              <?php foreach($digitalCertificates as $cRow): 
                $cType = $cRow['cert_type'] ?? 'Ownership Certificate';
                $isExp = !empty($cRow['expiry_date']) && strtotime($cRow['expiry_date']) < time();
                $cBadgeClr = $isExp ? '#ef4444' : '#10b981';
                $cIcon = match($cType) {
                  'Veterinary Health Certificate' => 'bi-heart-pulse-fill',
                  'Vaccination Certificate' => 'bi-shield-shaded',
                  'Shipping Permit' => 'bi-truck',
                  'Laboratory Result' => 'bi-radioactive',
                  'Insurance Document' => 'bi-shield-check',
                  'Registration', 'Renewal' => 'bi-award-fill',
                  default => 'bi-patch-check-fill'
                };
              ?>
              <div class="cert-item-card" data-cert-type="<?= htmlspecialchars($cType) ?>" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1rem;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 1px 3px rgba(0,0,0,.03);transition:transform .15s,box-shadow .15s;">
                <div>
                  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem;">
                    <div style="width:36px;height:36px;border-radius:8px;background:<?= $cBadgeClr ?>15;color:<?= $cBadgeClr ?>;display:flex;align-items:center;justify-content:center;font-size:1.15rem;">
                      <i class="bi <?= $cIcon ?>"></i>
                    </div>
                    <span style="background:<?= $cBadgeClr ?>15;color:<?= $cBadgeClr ?>;font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:99px;border:1px solid <?= $cBadgeClr ?>40;">
                      <?= $isExp ? 'EXPIRED' : (empty($cRow['status']) ? 'ACTIVE' : htmlspecialchars($cRow['status'])) ?>
                    </span>
                  </div>

                  <h4 style="font-size:.88rem;font-weight:700;color:#0f172a;margin:0 0 .25rem;line-height:1.3;">
                    <?= htmlspecialchars($cRow['document_title'] ?: $cType) ?>
                  </h4>
                  <div style="font-size:.74rem;color:#64748b;margin-bottom:.5rem;">
                    <div><i class="bi bi-tag"></i> <strong><?= htmlspecialchars($cRow['cert_number']) ?></strong></div>
                    <div><i class="bi bi-house"></i> <?= htmlspecialchars($cRow['farm_name'] ?? 'General Farm') ?></div>
                    <div><i class="bi bi-building"></i> <?= htmlspecialchars($cRow['issued_by'] ?? 'Department of Agriculture') ?></div>
                  </div>
                </div>

                <div style="border-top:1px solid #f1f5f9;padding-top:.6rem;margin-top:.4rem;display:flex;align-items:center;justify-content:space-between;font-size:.72rem;">
                  <span style="color:#64748b;">
                    Expires: <?= !empty($cRow['expiry_date']) ? date('M d, Y', strtotime($cRow['expiry_date'])) : 'Indefinite' ?>
                  </span>
                  <?php if(!empty($cRow['file_url'])): ?>
                    <a href="<?= htmlspecialchars(app_url($cRow['file_url'])) ?>" target="_blank" class="btn btn-secondary" style="padding:.25rem .55rem;font-size:.72rem;display:inline-flex;align-items:center;gap:.3rem;text-decoration:none;">
                      <i class="bi bi-file-earmark-arrow-down"></i> View
                    </a>
                  <?php else: ?>
                    <span style="color:#94a3b8;">System Cert</span>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          
        </div>
      </div>

      <!-- ===== PROFILE ===== -->
      <div id="pg-profile" class="page">
        <div class="profile-card-wrapper">
          
          <!-- Top Green Header -->
          <div class="profile-card-header">
            <div class="profile-avatar-circle" id="pfAvatarDisplay">
              <?php if(!empty($profileData['profile_pix']) && file_exists($profileData['profile_pix'])): ?>
                <img src="<?= htmlspecialchars($profileData['profile_pix']) ?>" alt="Profile Photo">
              <?php else: ?>
                <?= strtoupper(substr($firstName,0,1)) ?>
              <?php endif; ?>
            </div>
            <h2 class="profile-header-name"><?= htmlspecialchars($firstName.' '.$lastName) ?></h2>
            <div class="profile-header-role">
              <span>Registered Farmer</span>
              <span class="profile-verified-chip"><i class="bi bi-patch-check-fill"></i> Verified</span>
            </div>
          </div>

          <!-- Card Body -->
          <div class="profile-card-body">
            
            <!-- Personal Information -->
            <div class="profile-sec-heading">
              <i class="bi bi-person-fill"></i> Personal Information
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Email</span>
              <span class="profile-info-value">
                <?= !empty($profileData['email']) ? htmlspecialchars($profileData['email']) : '  -  ' ?>
                <?php if(!empty($profileData['email'])): ?>
                  <button type="button" class="profile-copy-btn" onclick="copyTagId('<?= htmlspecialchars(addslashes($profileData['email'])) ?>', this)" title="Copy Email"><i class="bi bi-copy"></i></button>
                <?php endif; ?>
              </span>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Mobile</span>
              <span class="profile-info-value">
                <?= !empty($profileData['mobile']) ? htmlspecialchars($profileData['mobile']) : '  -  ' ?>
                <?php if(!empty($profileData['mobile'])): ?>
                  <button type="button" class="profile-copy-btn" onclick="copyTagId('<?= htmlspecialchars(addslashes($profileData['mobile'])) ?>', this)" title="Copy Mobile"><i class="bi bi-copy"></i></button>
                <?php endif; ?>
              </span>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Gender</span>
              <span class="profile-info-value"><?= !empty($profileData['gender']) ? htmlspecialchars(ucfirst($profileData['gender'])) : '  -  ' ?></span>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Date of Birth</span>
              <span class="profile-info-value"><?= !empty($profileData['dob']) ? date('M d, Y', strtotime($profileData['dob'])) : '  -  ' ?></span>
            </div>

            <!-- Farm & Identification -->
            <div class="profile-sec-heading">
              <i class="bi bi-briefcase-fill"></i> Farm &amp; Identification
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">RSBSA / Farmer ID</span>
              <span class="profile-info-value">
                <?= !empty($profileData['gov_id']) ? htmlspecialchars($profileData['gov_id']) : '  -  ' ?>
                <?php if(!empty($profileData['gov_id'])): ?>
                  <button type="button" class="profile-copy-btn" onclick="copyTagId('<?= htmlspecialchars(addslashes($profileData['gov_id'])) ?>', this)" title="Copy RSBSA ID"><i class="bi bi-copy"></i></button>
                <?php endif; ?>
              </span>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Account Status</span>
              <span class="profile-info-value" style="color:#059669;font-weight:700;"><i class="bi bi-check-circle-fill"></i> Active &amp; Verified</span>
            </div>

            <!-- Residence & Emergency Contact -->
            <div class="profile-sec-heading">
              <i class="bi bi-geo-alt-fill"></i> Location &amp; Emergency Contact
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Permanent Address</span>
              <span class="profile-info-value"><?= !empty($profileData['address']) ? htmlspecialchars($profileData['address']) : '  -  ' ?></span>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Emergency Contact</span>
              <span class="profile-info-value"><?= !empty($profileData['emergency_contact']) ? htmlspecialchars($profileData['emergency_contact']) : '  -  ' ?></span>
            </div>

            <!-- Actions -->
            <div class="profile-card-actions">
              <button type="button" class="profile-btn-edit" onclick="openModal('profileModal')">
                <i class="bi bi-pencil-square"></i> Edit Profile
              </button>
              <button type="button" class="profile-btn-pw" onclick="openModal('farmerPasswordModal')">
                <i class="bi bi-shield-lock"></i> Change Password
              </button>
            </div>

          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- MODALS -->
<!-- MODAL: ANIMAL HEALTH HISTORY & LOGGING -->
<div id="healthHistoryModal" class="modal-wrap">
  <div class="modal-box" style="max-width:720px;width:95%;">
    <div class="modal-hdr">
      <div>
        <h3 id="healthModalTitle" style="display:flex;align-items:center;gap:.5rem;margin:0;font-size:1.1rem;">
          <i class="bi bi-heart-pulse-fill" style="color:#059669;"></i> Animal Health History
        </h3>
        <span id="healthModalSubtitle" style="font-size:.78rem;color:var(--sub);">Tag ID:  -  &bull; Complete Medical &amp; Biosecurity Log</span>
      </div>
      <button class="modal-close" onclick="closeModal('healthHistoryModal')">&times;</button>
    </div>
    
    <div class="modal-body" style="max-height:75vh;overflow-y:auto;padding:1.25rem;">
      <!-- Animal Vitals Summary Strip -->
      <div id="healthModalVitals" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.65rem;background:#f8fafc;padding:.75rem;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:1.25rem;font-size:.78rem;">
        <div><span style="color:#64748b;display:block;">Condition</span><strong id="hmCond"> - </strong></div>
        <div><span style="color:#64748b;display:block;">Species / Breed</span><strong id="hmBreed"> - </strong></div>
        <div><span style="color:#64748b;display:block;">Registered Farm</span><strong id="hmFarm"> - </strong></div>
        <div><span style="color:#64748b;display:block;">Last Checkup</span><strong id="hmLastCheck"> - </strong></div>
        <div><span style="color:#64748b;display:block;">Next Scheduled</span><strong id="hmNextCheck" style="color:#059669;"> - </strong></div>
      </div>

      <!-- Log New Health Event Toggle Form -->
      <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:1rem;margin-bottom:1.25rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
          <strong style="font-size:.88rem;color:#0f172a;display:flex;align-items:center;gap:.4rem;">
            <i class="bi bi-plus-circle-fill" style="color:#059669;"></i> Log New Health Checkup / Event
          </strong>
        </div>
        <form id="healthRecordForm" onsubmit="saveHealthRecord(event)">
          <input type="hidden" name="livestock_id" id="hrLivestockId" value="">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-bottom:.65rem;">
            <div>
              <label class="form-label" style="font-size:.76rem;font-weight:600;display:block;margin-bottom:.2rem;">Event Category *</label>
              <select name="record_type" class="form-input" required style="width:100%;padding:.42rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.78rem;">
                <option value="Health Check"> Routine Health Check</option>
                <option value="Vaccination"> Vaccination</option>
                <option value="Deworming"> Deworming</option>
                <option value="Veterinary Treatment"> Veterinary Treatment</option>
                <option value="Other"> Other Observation</option>
              </select>
            </div>
            <div>
              <label class="form-label" style="font-size:.76rem;font-weight:600;display:block;margin-bottom:.2rem;">Health Condition *</label>
              <select name="health_condition" class="form-input" required style="width:100%;padding:.42rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.78rem;">
                <option value="Healthy"> Healthy</option>
                <option value="Sick"> Sick / Symptoms</option>
                <option value="Under Treatment"> Under Treatment</option>
                <option value="Critical"> Critical</option>
                <option value="Recovered"> Recovered</option>
                <option value="Deceased"> Deceased</option>
              </select>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-bottom:.65rem;">
            <div>
              <label class="form-label" style="font-size:.76rem;font-weight:600;display:block;margin-bottom:.2rem;">Medicine / Vaccine Administered</label>
              <input type="text" name="medicine_administered" placeholder="e.g. Ivermectin / Anthrax Vaccine" class="form-input" style="width:100%;padding:.42rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.78rem;">
            </div>
            <div>
              <label class="form-label" style="font-size:.76rem;font-weight:600;display:block;margin-bottom:.2rem;">Dosage / Application</label>
              <input type="text" name="dosage" placeholder="e.g. 5ml Sub-Q / 1 bolus" class="form-input" style="width:100%;padding:.42rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.78rem;">
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.65rem;margin-bottom:.65rem;">
            <div>
              <label class="form-label" style="font-size:.76rem;font-weight:600;display:block;margin-bottom:.2rem;">Administered By</label>
              <input type="text" name="administered_by" placeholder="Vet / Technician name" class="form-input" style="width:100%;padding:.42rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.78rem;">
            </div>
            <div>
              <label class="form-label" style="font-size:.76rem;font-weight:600;display:block;margin-bottom:.2rem;">Checkup Date *</label>
              <input type="date" name="checkup_date" value="<?= date('Y-m-d') ?>" required class="form-input" style="width:100%;padding:.42rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.78rem;">
            </div>
            <div>
              <label class="form-label" style="font-size:.76rem;font-weight:600;display:block;margin-bottom:.2rem;">Next Scheduled Checkup</label>
              <input type="date" name="next_checkup_date" class="form-input" style="width:100%;padding:.42rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.78rem;">
            </div>
          </div>

          <div style="margin-bottom:.65rem;">
            <label class="form-label" style="font-size:.76rem;font-weight:600;display:block;margin-bottom:.2rem;">Treatment Notes / Clinical Diagnosis</label>
            <input type="text" name="treatment_details" placeholder="Observed health status, wounds, recovery progress..." class="form-input" style="width:100%;padding:.42rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.78rem;">
          </div>

          <button type="submit" class="btn btn-primary" style="padding:.45rem .85rem;font-size:.8rem;display:inline-flex;align-items:center;gap:.35rem;">
            <i class="bi bi-save"></i> Save Health Record
          </button>
        </form>
      </div>

      <!-- Health History Timeline -->
      <h4 style="font-size:.88rem;font-weight:700;color:#0f172a;margin-bottom:.75rem;display:flex;align-items:center;gap:.4rem;">
        <i class="bi bi-clock-history" style="color:#2563eb;"></i> Complete Medical Timeline
      </h4>
      <div id="healthTimelineContainer" style="display:flex;flex-direction:column;gap:.65rem;">
        <div style="text-align:center;padding:1.5rem;color:#64748b;font-size:.8rem;">Loading health records...</div>
      </div>
    </div>
    
    <div class="modal-footer" style="padding:.75rem 1.25rem;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('healthHistoryModal')">Close</button>
    </div>
  </div>
</div>

<!-- MODAL: UPLOAD DIGITAL CERTIFICATE -->
<div id="uploadCertModal" class="modal-wrap">
  <div class="modal-box" style="max-width:520px;width:95%;">
    <div class="modal-hdr">
      <h3 style="display:flex;align-items:center;gap:.4rem;margin:0;font-size:1.05rem;">
        <i class="bi bi-upload" style="color:#059669;"></i> Upload Digital Certificate / Clearance
      </h3>
      <button class="modal-close" onclick="closeModal('uploadCertModal')">&times;</button>
    </div>
    <form id="uploadCertForm" onsubmit="uploadDigitalCert(event)" enctype="multipart/form-data">
      <div class="modal-body" style="padding:1.15rem;">
        <div style="margin-bottom:.75rem;">
          <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Associated Farm *</label>
          <select name="farm_id" class="form-input" required style="width:100%;padding:.45rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.82rem;">
            <?php foreach($farmsData as $f): ?>
              <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="margin-bottom:.75rem;">
          <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Certificate / Document Type *</label>
          <select name="cert_type" class="form-input" required style="width:100%;padding:.45rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.82rem;">
            <option value="Ownership Certificate">Ownership Certificate (Certificate of Ownership)</option>
            <option value="Veterinary Health Certificate">Veterinary Health Certificate (Clinical Clearance)</option>
            <option value="Vaccination Certificate">Vaccination Certificate / Card</option>
            <option value="Shipping Permit">Shipping Permit (BAI / DA Transport Clearance)</option>
            <option value="Laboratory Result">Laboratory Result (Blood / Serology / ASF Test)</option>
            <option value="Insurance Document">Insurance Document (PCIC Policy / Endorsement)</option>
            <option value="Other Clearance">Other Agricultural Clearance</option>
          </select>
        </div>

        <div style="margin-bottom:.75rem;">
          <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Document Title / Description</label>
          <input type="text" name="document_title" placeholder="e.g. 2026 Cattle Health Clearance - Lupi" class="form-input" style="width:100%;padding:.45rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.82rem;">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-bottom:.75rem;">
          <div>
            <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Certificate / Permit No.</label>
            <input type="text" name="cert_number" placeholder="e.g. BAI-VET-2026-081" class="form-input" style="width:100%;padding:.45rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.82rem;">
          </div>
          <div>
            <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Issuing Authority</label>
            <input type="text" name="issued_by" value="Bureau of Animal Industry - DA Region V" class="form-input" style="width:100%;padding:.45rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.82rem;">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-bottom:.75rem;">
          <div>
            <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Issue Date *</label>
            <input type="date" name="issued_date" value="<?= date('Y-m-d') ?>" required class="form-input" style="width:100%;padding:.45rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.82rem;">
          </div>
          <div>
            <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Expiry Date</label>
            <input type="date" name="expiry_date" class="form-input" style="width:100%;padding:.45rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.82rem;">
          </div>
        </div>

        <div style="margin-bottom:.75rem;">
          <label class="form-label" style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;">Certificate File (PDF or Image) *</label>
          <input type="file" name="cert_file" accept=".pdf,image/*" required class="form-input" style="width:100%;padding:.4rem;border-radius:6px;border:1px solid #cbd5e1;font-size:.78rem;">
        </div>
      </div>
      <div class="modal-footer" style="padding:.75rem 1.15rem;display:flex;justify-content:flex-end;gap:.5rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('uploadCertModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-arrow-up-fill"></i> Upload &amp; Monitor</button>
      </div>
    </form>
  </div>
</div>
<div id="profileModal" class="modal-wrap">
  <div class="modal-box">
    <div class="modal-hdr">
      <h3><i class="bi bi-person-gear"></i> Edit Profile Information</h3>
      <button class="modal-close" onclick="closeModal('profileModal')">&times;</button>
    </div>
    <form id="profileForm" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="update_profile" value="1">
      <div class="modal-body">
        
        <!-- Live Avatar Selection Row -->
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;padding:0.85rem;background:#f8fafc;border-radius:14px;border:1px solid var(--border);">
          <div class="pf-preview-avatar" id="pfEditLiveAvatar">
            <?php if(!empty($profileData['profile_pix']) && file_exists($profileData['profile_pix'])): ?>
            <img src="<?= htmlspecialchars($profileData['profile_pix']) ?>" alt="Preview">
            <?php else: ?>
            <i class="bi bi-person-fill"></i>
            <?php endif; ?>
          </div>
          <div style="flex:1;">
            <label class="form-label" style="margin-bottom:3px;font-size:0.8rem;">Profile Photo</label>
            <input type="file" class="form-input" id="pfPhotoInput" name="profile_pix" accept="image/*" onchange="previewProfilePhoto(this)" style="padding:0.4rem;font-size:0.78rem;">
            <div style="font-size:0.68rem;color:var(--sub);margin-top:3px;">JPG, PNG, WebP up to 5MB</div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">First Name *</label>
            <input type="text" class="form-input" name="firstName" value="<?= htmlspecialchars($firstName) ?>" required>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Last Name *</label>
            <input type="text" class="form-input" name="lastName" value="<?= htmlspecialchars($lastName) ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">RSBSA / Farmer Registry ID</label>
          <input type="text" class="form-input" name="gov_id" value="<?= htmlspecialchars($profileData['gov_id']??'') ?>" placeholder="e.g. 05-17-01-000-001234">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Email Address *</label>
            <input type="email" class="form-input" name="email" value="<?= htmlspecialchars($profileData['email']??'') ?>" required>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Mobile Contact</label>
            <input type="tel" class="form-input" name="mobile" value="<?= htmlspecialchars($profileData['mobile']??'') ?>" placeholder="+63 9XX XXX XXXX">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Gender</label>
            <select class="form-select" name="gender">
              <option value="">Select Gender</option>
              <option value="Male" <?= ($profileData['gender']??'')==='Male'?'selected':'' ?>>Male</option>
              <option value="Female" <?= ($profileData['gender']??'')==='Female'?'selected':'' ?>>Female</option>
              <option value="Other" <?= ($profileData['gender']??'')==='Other'?'selected':'' ?>>Other</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Date of Birth</label>
            <input type="date" class="form-input" name="dob" value="<?= htmlspecialchars($profileData['dob']??'') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Home / Permanent Address</label>
          <textarea class="form-input" name="address" rows="2" placeholder="Barangay, Municipality, Province"><?= htmlspecialchars($profileData['address']??'') ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Emergency Contact Person &amp; Mobile</label>
          <input type="text" class="form-input" name="emergency_contact" value="<?= htmlspecialchars($profileData['emergency_contact']??'') ?>" placeholder="e.g. Maria Santos (0917-123-4567)">
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('profileModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="btnSaveProfile"><i class="bi bi-check2"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Change Password Modal -->
<div id="farmerPasswordModal" class="modal-wrap">
  <div class="modal-box" style="max-width:460px;">
    <div class="modal-hdr">
      <h3><i class="bi bi-shield-lock"></i> Change Password</h3>
      <button class="modal-close" onclick="closeModal('farmerPasswordModal')">&times;</button>
    </div>
    <form id="farmerPwForm" autocomplete="off">
      <input type="hidden" name="action" value="change_password">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:1rem;">
          <label class="form-label">Current Password *</label>
          <input type="password" class="form-input" name="current_password" required placeholder="Enter current password">
        </div>
        <div class="form-group" style="margin-bottom:1rem;">
          <label class="form-label">New Password * (minimum 6 characters)</label>
          <input type="password" class="form-input" name="new_password" required minlength="6" placeholder="Enter new strong password">
        </div>
        <div class="form-group" style="margin-bottom:1.25rem;">
          <label class="form-label">Confirm New Password *</label>
          <input type="password" class="form-input" name="confirm_password" required minlength="6" placeholder="Repeat new password">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('farmerPasswordModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="btnUpdatePw"><i class="bi bi-key-fill"></i> Update Password</button>
      </div>
    </form>
  </div>
</div>

<div id="lvEditModal" class="modal-wrap">
  <div class="modal-box">
    <div class="modal-hdr"><h3 id="lvEditTitle"><i class="bi bi-pencil-square"></i> Edit Livestock</h3><button class="modal-close" onclick="closeModal('lvEditModal')">&times;</button></div>
    <form id="lvEditForm">
      <input type="hidden" id="lvEditId" name="livestock_id">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Tag ID *</label><input type="text" class="form-input" id="lvEditTag" name="tagId" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group"><label class="form-label">Type *</label>
            <select class="form-select" id="lvEditType" name="type" required onchange="updateBreedDropdown('edit')">
              <?php foreach($allAnimalTypes as $at): ?><option><?= $at ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Farming Type *</label>
            <select class="form-select" id="lvEditFarmingType" name="farming_type" required>
              <option value="Backyard">Backyard (Smallholder)</option>
              <option value="Commercial">Commercial (Agribusiness)</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Breed / Production Type</label>
          <select class="form-select" id="lvEditBreedSelect" onchange="onBreedSelectChange('edit')">
            <option value="">Select type first...</option>
          </select>
          <input type="text" class="form-input" id="lvEditBreedCustom" placeholder="Specify custom breed..." style="display:none;margin-top:6px;" oninput="syncBreedFinal('edit')">
          <input type="hidden" name="breed" id="lvEditBreed">
        </div>
        <div class="form-group"><label class="form-label">Quantity *</label><input type="number" class="form-input" id="lvEditQty" name="qty" min="1" required></div>
        <div class="form-group"><label class="form-label">Age (years)</label><input type="number" class="form-input" id="lvEditAge" name="age" min="0" step="0.1"></div>
        <div class="form-group"><label class="form-label">Weight (kg)</label><input type="number" class="form-input" id="lvEditWeight" name="weight" step="0.1"></div>
        <div class="form-group"><label class="form-label">Health Status</label>
          <select class="form-select" id="lvEditHealth" name="healthStatus">
            <option value="Healthy">Healthy</option>
            <option value="Sick">Sick</option>
            <option value="Recovered">Recovered</option>
            <option value="Deceased">Deceased</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Vaccination</label>
          <select class="form-select" id="lvEditVacc" name="vaccinationStatus">
            <option value="None">None</option>
            <option value="Partial">Partial</option>
            <option value="Complete">Complete</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Medical Notes / Treatment History</label>
          <textarea class="form-input" id="lvEditNotes" name="notes" rows="2" placeholder="e.g. Dewormed, medications, observation notes..."></textarea>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('lvEditModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button></div>
    </form>
  </div>
</div>

<div id="lvAddModal" class="modal-wrap">
  <div class="modal-box" style="max-width:540px;">
    <div class="modal-hdr">
      <h3><i class="bi bi-tag-fill" style="color:var(--acc2);"></i> Add Livestock &amp; Tag Registry</h3>
      <button class="modal-close" onclick="closeModal('lvAddModal')">&times;</button>
    </div>
    <form id="lvAddForm">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Farm *</label>
          <select class="form-select" name="farm_id" id="lvAddFarm" required>
            <option value="">Select farm...</option>
            <?php foreach($farmsData as $f): ?>
            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> (<?= $f['type'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group"><label class="form-label">Animal Species *</label>
            <select class="form-select" id="lvAddType" name="type" required onchange="autoFillTagId(); updateBreedDropdown('add');">
              <option value="">Select species...</option>
              <?php foreach($allAnimalTypes as $at): ?><option><?= $at ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Type / Category *</label>
            <select class="form-select" id="lvAddFarmingType" name="farming_type" required>
              <option value="Backyard" selected>Backyard (Smallholder)</option>
              <option value="Commercial">Commercial (Agribusiness)</option>
            </select>
          </div>
        </div>

        <!-- INDIVIDUAL TAG SECTION -->
        <div class="form-group" style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:12px;padding:12px 14px;margin-bottom:1rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <label class="form-label" style="margin:0;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:6px;">
              <i class="bi bi-upc-scan" style="color:#059669;"></i> Individual Tag / Physical Tag # *
            </label>
            <span style="font-size:0.7rem;background:#d1fae5;color:#065f46;padding:2px 7px;border-radius:99px;font-weight:700;">
              Ear Tag Registry
            </span>
          </div>
          <div style="display:flex;gap:6px;align-items:stretch;">
            <input type="text" class="form-input" id="lvAddTagId" name="tagId" required placeholder="Type tag #, scan, or auto-generate" style="font-family:monospace;font-weight:700;letter-spacing:0.5px;">
            <button type="button" class="btn btn-secondary" style="padding:0.45rem 0.8rem;font-size:0.78rem;flex-shrink:0;display:inline-flex;align-items:center;gap:4px;" onclick="generateRandomTagId()" title="Automatically Generate Tag ID">
              <i class="bi bi-arrow-clockwise"></i> Auto-Gen
            </button>
            <button type="button" class="btn btn-secondary" style="padding:0.45rem 0.8rem;font-size:0.78rem;flex-shrink:0;background:#ecfdf5;color:#059669;border-color:#a7f3d0;display:inline-flex;align-items:center;gap:4px;" onclick="openTagScanner('lvAddTagId')" title="Scan Barcode / QR Code on Physical Ear Tag">
              <i class="bi bi-qr-code-scan"></i> Scan
            </button>
          </div>
          <div style="font-size:0.72rem;color:#64748b;margin-top:6px;line-height:1.4;">
            <i class="bi bi-info-circle"></i> Enter the physical ear tag stamped on the animal, use your camera to scan its barcode/QR tag, or tap <b>Auto-Gen</b> to generate an official tag ID.
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Breed / Production Type</label>
            <select class="form-select" id="lvAddBreedSelect" onchange="onBreedSelectChange('add')">
              <option value="">Select species first...</option>
            </select>
            <input type="text" class="form-input" id="lvAddBreedCustom" placeholder="Specify custom breed..." style="display:none;margin-top:6px;" oninput="syncBreedFinal('add')">
            <input type="hidden" name="breed" id="lvAddBreed">
          </div>
          <div class="form-group"><label class="form-label">Quantity (Heads) *</label><input type="number" class="form-input" name="qty" value="1" min="1" required></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group"><label class="form-label">Age (years)</label><input type="number" class="form-input" name="age" min="0" step="0.1" placeholder="e.g. 1.5"></div>
          <div class="form-group"><label class="form-label">Weight (kg)</label><input type="number" class="form-input" name="weight" step="0.1" placeholder="e.g. 75.0"></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group"><label class="form-label">Health Status</label>
            <select class="form-select" name="healthStatus">
              <option value="Healthy" selected>Healthy</option>
              <option value="Sick">Sick</option>
              <option value="Recovered">Recovered</option>
              <option value="Deceased">Deceased</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Vaccination Status</label>
            <select class="form-select" name="vaccinationStatus">
              <option value="None" selected>None</option>
              <option value="Partial">Partial</option>
              <option value="Complete">Complete</option>
            </select>
          </div>
        </div>

        <div class="form-group"><label class="form-label">Medical Notes / Remarks</label>
          <textarea class="form-input" name="notes" rows="2" placeholder="e.g. Dewormed, booster schedule, ear tag markings, vitamins..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('lvAddModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Livestock</button>
      </div>
    </form>
  </div>
</div>

<!-- EAR TAG BARCODE/QR SCANNER MODAL -->
<div id="tagScanModal" class="modal-wrap" style="z-index:9999;">
  <div class="modal-box" style="max-width:440px;padding:0;overflow:hidden;border-radius:20px;background:#0f172a;color:#fff;">
    <div style="padding:14px 18px;display:flex;justify-content:space-between;align-items:center;background:#1e293b;border-bottom:1px solid rgba(255,255,255,0.1);">
      <div style="display:flex;align-items:center;gap:8px;">
        <i class="bi bi-qr-code-scan" style="color:#10b981;font-size:1.1rem;"></i>
        <strong style="font-size:0.95rem;color:#fff;">Scan Ear Tag / Barcode</strong>
      </div>
      <button type="button" style="background:none;border:none;color:#94a3b8;font-size:1.4rem;cursor:pointer;line-height:1;" onclick="closeTagScanner()">&times;</button>
    </div>
    <div style="position:relative;background:#000;width:100%;height:320px;overflow:hidden;display:flex;align-items:center;justify-content:center;">
      <video id="tagScanVideo" autoplay playsinline style="width:100%;height:100%;object-fit:cover;"></video>
      <canvas id="tagScanCanvas" style="display:none;"></canvas>
      
      <!-- Scan reticle / viewfinder -->
      <div style="position:absolute;inset:40px;border:2px solid #10b981;border-radius:12px;box-shadow:0 0 0 9999px rgba(0,0,0,0.5);pointer-events:none;display:flex;flex-direction:column;justify-content:space-between;">
        <div style="display:flex;justify-content:space-between;padding:4px;">
          <span style="width:14px;height:14px;border-top:3px solid #10b981;border-left:3px solid #10b981;"></span>
          <span style="width:14px;height:14px;border-top:3px solid #10b981;border-right:3px solid #10b981;"></span>
        </div>
        <div style="text-align:center;color:#fff;font-size:0.75rem;background:rgba(0,0,0,0.6);padding:3px 8px;border-radius:6px;margin:0 10px;">
          Align Ear Tag Barcode or QR Code
        </div>
        <div style="display:flex;justify-content:space-between;padding:4px;">
          <span style="width:14px;height:14px;border-bottom:3px solid #10b981;border-left:3px solid #10b981;"></span>
          <span style="width:14px;height:14px;border-bottom:3px solid #10b981;border-right:3px solid #10b981;"></span>
        </div>
      </div>
      <div id="tagScanStatus" style="position:absolute;bottom:10px;left:0;right:0;text-align:center;font-size:0.75rem;color:#cbd5e1;">
        Aim camera at physical ear tag...
      </div>
    </div>
      <div style="text-align:left;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:.85rem;margin-bottom:1.5rem;">
        <strong style="font-size:.76rem;color:#334155;display:block;margin-bottom:.5rem;">PROCESSING TIMELINE:</strong>
        <div style="display:flex;align-items:center;justify-content:space-between;position:relative;font-size:.7rem;font-weight:700;">
          <div style="text-align:center;color:#059669;">
            <div style="width:20px;height:20px;border-radius:50%;background:#059669;color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 3px;">1</div>
            Submitted
          </div>
          <div style="flex:1;height:2px;background:#cbd5e1;margin:0 4px 14px;"></div>
          <div style="text-align:center;color:#64748b;">
            <div style="width:20px;height:20px;border-radius:50%;background:#e2e8f0;color:#64748b;display:flex;align-items:center;justify-content:center;margin:0 auto 3px;">2</div>
            Review
          </div>
          <div style="flex:1;height:2px;background:#cbd5e1;margin:0 4px 14px;"></div>
          <div style="text-align:center;color:#64748b;">
            <div style="width:20px;height:20px;border-radius:50%;background:#e2e8f0;color:#64748b;display:flex;align-items:center;justify-content:center;margin:0 auto 3px;">3</div>
            Inspection
          </div>
          <div style="flex:1;height:2px;background:#cbd5e1;margin:0 4px 14px;"></div>
          <div style="text-align:center;color:#64748b;">
            <div style="width:20px;height:20px;border-radius:50%;background:#e2e8f0;color:#64748b;display:flex;align-items:center;justify-content:center;margin:0 auto 3px;">4</div>
            Cert Issued
          </div>
        </div>
      </div>

      <div style="display:flex;gap:.75rem;justify-content:center;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalAssistanceSuccess'); location.reload();">Close</button>
        <button type="button" class="btn btn-primary" onclick="closeModal('modalAssistanceSuccess'); switchAssistanceTab('history');">View in My Requests</button>
      </div>
    </div>
  </div>
</div>

<!-- ================= MODAL: FARMER VIEW ASSISTANCE DETAILS ================= -->
<div id="modalFarmerAssistanceDetails" class="modal-wrap">
  <div class="modal-box" style="max-width:720px;width:95%;">
    <div class="modal-hdr">
      <div>
        <h3 id="farmerAssistDetailTitle" style="margin:0;font-size:1.05rem;display:flex;align-items:center;gap:.45rem;">
          <i class="bi bi-file-text-fill" style="color:#059669;"></i> Assistance Request Details
        </h3>
        <span id="farmerAssistDetailSubtitle" style="font-size:.76rem;color:#64748b;">Reference No &bull; Tracking &amp; Official Notes</span>
      </div>
      <button class="modal-close" onclick="closeModal('modalFarmerAssistanceDetails')">&times;</button>
    </div>
    <div class="modal-body" style="padding:1.25rem;max-height:75vh;overflow-y:auto;" id="farmerAssistDetailBody">
      <!-- Loaded dynamically via JS -->
    </div>
    <div class="modal-footer" style="padding:.75rem 1.25rem;display:flex;justify-content:space-between;align-items:center;">
      <div id="farmerAssistModalCertButtons" style="display:flex;gap:.5rem;"></div>
      <button type="button" class="btn btn-secondary" onclick="closeModal('modalFarmerAssistanceDetails')">Close</button>
    </div>
  </div>
</div>

<!-- ================= MODAL: OFFICIAL CERTIFICATE VIEW / PRINT (VHC & COI) ================= -->
<div id="modalFarmerCertificateView" class="modal-wrap">
  <div class="modal-box" style="max-width:850px;width:95%;">
    <div class="modal-hdr no-print" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
      <div>
        <h3 id="certModalTitle" style="margin:0;font-size:1.05rem;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:.45rem;">
          <i class="bi bi-patch-check-fill" style="color:#059669;"></i> Official Certificate Document
        </h3>
        <span id="certModalSubtitle" style="font-size:.76rem;color:#64748b;">Republic of the Philippines &bull; Department of Agriculture Official Copy</span>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
        <button type="button" class="btn btn-sm btn-secondary" onclick="printOfficialCertificateDocument()" style="padding:.35rem .75rem;font-size:.78rem;font-weight:700;">
          <i class="bi bi-printer-fill"></i> Print
        </button>
        <button type="button" class="btn btn-sm btn-primary" id="btnFarmerCertDownloadPdf" onclick="downloadFarmerOfficialCertPdf()" style="padding:.35rem .75rem;font-size:.78rem;font-weight:700;">
          <i class="bi bi-file-earmark-pdf-fill"></i> Download PDF
        </button>
        <button class="modal-close" onclick="closeModal('modalFarmerCertificateView')">&times;</button>
      </div>
    </div>
    <div class="modal-body" style="padding:1.5rem;max-height:78vh;overflow-y:auto;background:#e2e8f0;" id="certPrintDocumentWrapper">
      <!-- Printable Certificate Content Rendered Here -->
    </div>
    <div class="modal-footer no-print" style="padding:.75rem 1.25rem;display:flex;justify-content:space-between;">
      <small style="color:#64748b;font-size:.74rem;">Digital security verified with AgriTrace+ official database.</small>
      <button type="button" class="btn btn-secondary" onclick="closeModal('modalFarmerCertificateView')">Close</button>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script>
const CURRENT_USER_ID = <?= json_encode((int)$userId) ?>;
const APP_BASE = <?= json_encode(app_base()) ?>;
const CURRENT_NAME    = <?= json_encode(trim($firstName.' '.$lastName)) ?>;

let mediaStream=null, camFacing='environment', camMode='farm', currentGPS=null;

// -------------------------------- NAVIGATION --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
function toggleDrawerDropdown(id) {
  const sub = document.getElementById(id);
  const btn = document.getElementById('btn-' + id);
  if (!sub || !btn) return;
  const isOpen = sub.classList.contains('open');
  // Auto-close other dropdowns for clean single-expanded behavior
  document.querySelectorAll('.drawer-submenu').forEach(s => {
    if (s.id !== id) {
      s.classList.remove('open');
      const b = document.getElementById('btn-' + s.id);
      if (b) { b.classList.remove('open'); b.setAttribute('aria-expanded', 'false'); }
    }
  });
  if (isOpen) {
    sub.classList.remove('open');
    btn.classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
  } else {
    sub.classList.add('open');
    btn.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
  }
}

function navTo(page){
  if (page === 'slaughter') {
    navTo('vet-request');
    setTimeout(() => { setAssistanceType('Slaughter Assistance'); }, 50);
    return;
  }

  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.drawer-item[data-page], .drawer-subitem[data-page]').forEach(t=>t.classList.remove('active'));
  
  const pg=document.getElementById('pg-'+page);
  if(pg) pg.classList.add('active');

  const targetNav = document.querySelector(`.drawer-item[data-page="${page}"], .drawer-subitem[data-page="${page}"]`);
  if (targetNav) targetNav.classList.add('active');

  // Auto-open parent dropdown if page is a subitem
  if (['farm-reg', 'rsbsa-form', 'pcic-form'].includes(page)) {
    const sub = document.getElementById('drop-reg');
    const btn = document.getElementById('btn-drop-reg');
    if (sub && !sub.classList.contains('open')) {
      sub.classList.add('open');
      btn?.classList.add('open');
      btn?.setAttribute('aria-expanded', 'true');
    }
  } else if (['vet-request'].includes(page)) {
    const sub = document.getElementById('drop-services');
    const btn = document.getElementById('btn-drop-services');
    if (sub && !sub.classList.contains('open')) {
      sub.classList.add('open');
      btn?.classList.add('open');
      btn?.setAttribute('aria-expanded', 'true');
    }
  }

  const titles={
    dashboard:'Dashboard',
    'farm-reg':'Farm Registration',
    'rsbsa-form':'DA-RSBSA Enrollment Form',
    'pcic-form':'PCIC Livestock Mortality Insurance Form',
    livestock:'Livestock & Health Monitoring',
    'vet-request':'Request Veterinary Assistance',
    insurance:'PCIC Livestock Insurance Assistance',
    slaughter:'Slaughter Activity Declarations',
    ecert:'Digital Certificate Management',
    incident:'Incident Report',
    notifications:'Notifications',
    chat:'Farmer Chat',
    profile:'My Profile'
  };
  const titleEl = document.getElementById('pageTitle');
  if(titleEl) titleEl.textContent=titles[page]||'AgriTrace+';
  const ca = document.getElementById('contentArea');
  if(ca) ca.scrollTop=0;
  closeDrawer();
  if(page==='chat'){loadFarmerList();pollUnread();}
  if(page==='ecert'){buildCertQRs();}
  if(page==='livestock'){setTimeout(initLivestockCharts, 120);}
}

function submitPcicForm(e) {
  e.preventDefault();
  const form = document.getElementById('pcicApplicationForm');
  const btn = document.getElementById('btnSubmitPcic');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...'; }
  const fd = new FormData(form);
  fetch('farmer.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(data.message || 'PCIC Insurance application submitted successfully!', 'success');
        setTimeout(() => location.reload(), 1500);
      } else {
        showToast(data.message || 'Submission failed.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-cloud-arrow-up-fill"></i> Submit PCIC Application'; }
      }
    })
    .catch(err => {
      showToast('Error: ' + err.message, 'error');
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-cloud-arrow-up-fill"></i> Submit PCIC Application'; }
    });
}

function submitRsbsaForm(e) {
  e.preventDefault();
  const form = document.getElementById('rsbsaEnrollmentForm');
  const btn = document.getElementById('btnSubmitRsbsa');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...'; }
  const fd = new FormData(form);
  fetch('farmer.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(data.message || 'RSBSA Enrollment Form submitted successfully!', 'success');
        setTimeout(() => location.reload(), 1500);
      } else {
        showToast(data.message || 'Submission failed.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-cloud-arrow-up-fill"></i> Submit RSBSA Form'; }
      }
    })
    .catch(err => {
      showToast('Error: ' + err.message, 'error');
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-cloud-arrow-up-fill"></i> Submit RSBSA Form'; }
    });
}

function downloadFolioPdf(areaId, defaultFilename) {
  const el = document.getElementById(areaId);
  if (!el) return;
  showToast('Generating 8.5" x 13" PDF document...', 'info');
  if (typeof html2pdf !== 'undefined') {
    const opt = {
      margin: [0.35, 0.4, 0.35, 0.4],
      filename: defaultFilename || 'Form_Document.pdf',
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true, logging: false },
      jsPDF: { unit: 'in', format: [8.5, 13], orientation: 'portrait' }
    };
    html2pdf().set(opt).from(el).save().then(() => {
      showToast('PDF downloaded successfully!', 'success');
    }).catch(e => {
      window.print();
    });
  } else {
    window.print();
  }
}
document.querySelectorAll('.drawer-item[data-page]').forEach(t=>t.addEventListener('click',()=>navTo(t.dataset.page)));

// Fail-safe delegation on drawer-nav for mobile touch & click events
document.querySelector('.drawer-nav')?.addEventListener('click', (e) => {
  const sub = e.target.closest('.drawer-subitem[data-page]');
  if (sub && sub.dataset.page) {
    e.preventDefault();
    navTo(sub.dataset.page);
    return;
  }
  const item = e.target.closest('.drawer-item[data-page]');
  if (item && item.dataset.page) {
    e.preventDefault();
    navTo(item.dataset.page);
  }
});

function openDrawer(){
  const d = document.getElementById('drawer');
  if (!d) return;
  if (window.innerWidth >= 1024 && document.body.classList.contains('sidebar-autohide')) {
    d.classList.add('hover-open');
  } else {
    d.classList.add('open');
    document.getElementById('drawerOverlay')?.classList.add('show');
  }
}

function closeDrawer(){
  const d = document.getElementById('drawer');
  if (!d) return;
  d.classList.remove('open', 'hover-open');
  document.getElementById('drawerOverlay')?.classList.remove('show');
}

function toggleDrawer(){
  const d = document.getElementById('drawer');
  if (!d) return;
  if (window.innerWidth >= 1024 && document.body.classList.contains('sidebar-autohide')) {
    d.classList.toggle('hover-open');
  } else {
    const isNowOpen = d.classList.toggle('open');
    document.getElementById('drawerOverlay')?.classList.toggle('show', isNowOpen);
  }
}

// -------------------------------- AUTO-HIDE & FULL VIEW CONTROLLER ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
const farmerDrawer = document.getElementById('drawer');
const farmerHoverZone = document.getElementById('farmerHoverZone');
const farmerEdgeTrigger = document.getElementById('farmerEdgeTrigger');
const farmerPinBtn = document.getElementById('farmerPinBtn');

let isFarmerAutoHide = localStorage.getItem('agritrace_farmer_autohide') !== 'false';

function applyFarmerAutoHide(enabled) {
  isFarmerAutoHide = enabled;
  localStorage.setItem('agritrace_farmer_autohide', enabled ? 'true' : 'false');
  if (enabled) {
    document.body.classList.add('sidebar-autohide');
    if (farmerPinBtn) farmerPinBtn.innerHTML = '<i class="bi bi-pin-angle" title="Click to Pin Sidebar Open"></i>';
  } else {
    document.body.classList.remove('sidebar-autohide');
    farmerDrawer?.classList.remove('hover-open');
    if (farmerPinBtn) farmerPinBtn.innerHTML = '<i class="bi bi-pin-fill" title="Click to Auto-Hide for Full View"></i>';
  }
}
applyFarmerAutoHide(isFarmerAutoHide);

farmerPinBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  applyFarmerAutoHide(!isFarmerAutoHide);
});

let farmerHoverTimer = null;
function openFarmerHover() {
  if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth < 1024) return;
  clearTimeout(farmerHoverTimer);
  farmerDrawer?.classList.add('hover-open');
}
function closeFarmerHover() {
  if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth < 1024) return;
  clearTimeout(farmerHoverTimer);
  farmerHoverTimer = setTimeout(() => {
    farmerDrawer?.classList.remove('hover-open');
  }, 300);
}

farmerHoverZone?.addEventListener('mouseenter', openFarmerHover);
farmerEdgeTrigger?.addEventListener('mouseenter', openFarmerHover);
farmerEdgeTrigger?.addEventListener('click', (e) => {
  e.stopPropagation();
  farmerDrawer?.classList.toggle('hover-open');
});
farmerDrawer?.addEventListener('mouseenter', () => clearTimeout(farmerHoverTimer));
farmerDrawer?.addEventListener('mouseleave', closeFarmerHover);

document.addEventListener('mousemove', (e) => {
  if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth < 1024) return;
  if (e.clientX <= 14) openFarmerHover();
});
function openModal(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';}
function showToast(msg,type='success'){const t=document.getElementById('toast');t.textContent=msg;t.className='toast toast-'+type+' show';clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),3200);}

// -------------------------------- GPS & DISTRICT BOUNDS --------------------------------
const DISTRICT_BOUNDS = {
  minLat: 13.645, maxLat: 13.955,
  minLng: 122.505, maxLng: 123.180,
  center: [13.803, 122.858]
};
let rawFarmCanvas = null;
let rawIncCanvas = null;
const pickerState = {
  farm:     { engine: null, map: null, marker: null, accCircle: null, gmap: null, gmarker: null, gcircle: null, grect: null, hybrid: null, street: null, isHybrid: true, dragged: false, lastGps: null },
  incident: { engine: null, map: null, marker: null, accCircle: null, gmap: null, gmarker: null, gcircle: null, grect: null, hybrid: null, street: null, isHybrid: true, dragged: false, lastGps: null }
};

function isWithinDistrictBounds(lat, lng) {
  const nLat = Number(lat), nLng = Number(lng);
  if (!Number.isFinite(nLat) || !Number.isFinite(nLng)) return false;
  if (nLat === 0 && nLng === 0) return false;
  return nLat >= DISTRICT_BOUNDS.minLat && nLat <= DISTRICT_BOUNDS.maxLat &&
         nLng >= DISTRICT_BOUNDS.minLng && nLng <= DISTRICT_BOUNDS.maxLng;
}

function setCamGpsIndicator(text, stateClass = '') {
  const box = document.getElementById('camGpsLive');
  const lbl = document.getElementById('camGpsLiveText');
  if (lbl) lbl.textContent = text;
  if (box) {
    box.classList.remove('good', 'err');
    if (stateClass) box.classList.add(stateClass);
  }
}

function getGPS(onProgress) {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('Geolocation is not supported by this browser. Please pin your location manually on the map.'));
      return;
    }
    let watchId = null;
    let bestFix = null;
    let settled = false;
    let lastError = null;

    const cleanup = () => {
      if (watchId !== null) {
        try { navigator.geolocation.clearWatch(watchId); } catch (e) {}
        watchId = null;
      }
      clearTimeout(collectTimer);
    };

    const finishSuccess = (fix) => {
      if (settled) return;
      settled = true;
      cleanup();
      resolve(fix);
    };

    const finishError = (err) => {
      if (settled) return;
      settled = true;
      cleanup();
      reject(err);
    };

    // Collect fixes for up to 20s and keep the smallest accuracy
    const collectTimer = setTimeout(() => {
      if (bestFix) {
        finishSuccess(bestFix);
      } else {
        const msg = lastError && lastError.code === 1
          ? 'Location permission denied. Enable GPS/Location permission or pin your location manually on the map.'
          : 'Unable to acquire a GPS fix within 20 seconds. Move outdoors to retry or pin your location manually on the map.';
        finishError(new Error(msg));
      }
    }, 20000);

    if (typeof onProgress === 'function') {
      onProgress({ status: 'Acquiring GPS...', acc: null });
    }

    watchId = navigator.geolocation.watchPosition(
      (pos) => {
        const acc = Math.round(pos.coords.accuracy);
        const fix = {
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          acc: acc,
          source: 'gps'
        };
        if (!bestFix || fix.acc < bestFix.acc) {
          bestFix = fix;
        }
        if (typeof onProgress === 'function') {
          onProgress({ status: `Acquiring GPS... +/-${bestFix.acc}m`, acc: bestFix.acc, fix: bestFix });
        }
        if (bestFix.acc <= 25) {
          finishSuccess(bestFix);
        }
      },
      (err) => {
        lastError = err;
        if (err.code === 1) {
          finishError(new Error('Location permission denied. Please allow location access or pin your location manually on the map.'));
        }
      },
      { enableHighAccuracy: true, maximumAge: 0, timeout: 30000 }
    );
  });
}

async function reverseGeocode(lat, lng) {
  try {
    if (window.google && window.google.maps && window.google.maps.Geocoder) {
      return new Promise((resolve) => {
        const gc = new google.maps.Geocoder();
        gc.geocode({ location: { lat: Number(lat), lng: Number(lng) } }, (res, status) => {
          if (status === 'OK' && res && res[0]) {
            resolve(res[0].formatted_address);
          } else {
            resolve(`${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`);
          }
        });
      });
    }
    const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1&accept-language=en`);
    const d = await r.json();
    return d.display_name || `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
  } catch (e) {
    return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
  }
}

function cloneCanvas(src) {
  const c = document.createElement('canvas');
  c.width = src.width;
  c.height = src.height;
  c.getContext('2d').drawImage(src, 0, 0);
  return c;
}

async function stampGPS(canvas, gps) {
  const ctx = canvas.getContext('2d');
  const ts = new Date().toLocaleString('en-PH', {
    timeZone: 'Asia/Manila',
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  });
  const h = Math.max(76, Math.floor(canvas.height * 0.19));
  const fs = Math.max(13, Math.floor(canvas.width / 42));
  ctx.fillStyle = 'rgba(0,0,0,0.82)';
  ctx.fillRect(0, canvas.height - h, canvas.width, h);
  ctx.fillStyle = '#ffffff';
  ctx.font = `bold ${fs}px sans-serif`;
  ctx.shadowColor = 'rgba(0,0,0,0.75)';
  ctx.shadowBlur = 3;

  let line1 = '';
  if (gps && gps.source === 'manual') {
    line1 = `GPS ${Number(gps.lat).toFixed(6)}, ${Number(gps.lng).toFixed(6)} (Location set manually)`;
  } else if (gps && Number.isFinite(Number(gps.lat)) && Number.isFinite(Number(gps.lng))) {
    const accTxt = Number.isFinite(Number(gps.acc)) ? ` (+/-${Math.round(Number(gps.acc))}m)` : '';
    const srcTxt = gps.source ? ` [${String(gps.source).toUpperCase()}]` : '';
    line1 = `GPS ${Number(gps.lat).toFixed(6)}, ${Number(gps.lng).toFixed(6)}${accTxt}${srcTxt}`;
  } else {
    line1 = 'GPS Location pending confirmation';
  }

  const hBox = Math.max(96, Math.floor(canvas.height * 0.23));
  ctx.clearRect(0, canvas.height - h, canvas.width, 0);
  ctx.fillStyle = 'rgba(15,23,42,0.82)';
  ctx.fillRect(0, canvas.height - hBox, canvas.width, hBox);
  ctx.fillStyle = '#67e8f9';
  ctx.font = `bold ${fs}px sans-serif`;
  ctx.fillText(`${ts} (Asia/Manila)`, 14, canvas.height - hBox + fs + 6);
  ctx.fillStyle = '#ffffff';
  ctx.font = `bold ${fs}px sans-serif`;
  ctx.fillText(line1, 14, canvas.height - hBox + fs * 2 + 11);
  const altTxt = (gps && Number.isFinite(Number(gps.alt))) ? `${Number(gps.alt).toFixed(1)}m` : '0.0m';
  const accLine = (gps && gps.source === 'manual')
    ? 'Source: Manual Map Pin | AgriTrace+ | Camarines Sur DA'
    : `Alt: ${altTxt} | Acc: ${(gps && Number.isFinite(Number(gps.acc))) ? '+/-' + Math.round(Number(gps.acc)) + 'm' : '--'} | AgriTrace+ | Camarines Sur DA`;
  ctx.fillStyle = '#e2e8f0';
  ctx.font = `${Math.floor(fs * 0.85)}px sans-serif`;
  ctx.fillText(accLine, 14, canvas.height - hBox + fs * 3 + 16);
  if (gps && gps.address) {
    ctx.fillStyle = '#cbd5e1';
    ctx.font = `${Math.floor(fs * 0.8)}px sans-serif`;
    ctx.fillText(String(gps.address).slice(0, 78), 14, canvas.height - hBox + fs * 4 + 20);
  }
  ctx.shadowBlur = 0;
}

function renderGpsFeedback(mode, type, htmlMsg) {
  const el = document.getElementById(mode === 'farm' ? 'farmGpsWarn' : 'incGpsWarn');
  if (!el) return;
  el.className = 'gps-warn-box' + (type ? ' ' + type : '');
  el.innerHTML = htmlMsg || '';
}

// -------------------------------- GOOGLE MAPS / LEAFLET LOCATION PICKER --------------------------------
function isGoogleMapsAvailable() {
  return typeof google !== 'undefined' && typeof google.maps !== 'undefined' && typeof google.maps.Map === 'function';
}

// Fallback to Leaflet if Google Maps authentication fails
window.gm_authFailure = function() {
  console.warn('Google Maps API key authentication failure. Switching location picker to Leaflet engine.');
  Object.keys(pickerState).forEach(mode => {
    const st = pickerState[mode];
    if (st && st.engine === 'google') {
      st.engine = 'leaflet';
      const badge = document.getElementById(mode === 'farm' ? 'farmMapEngineBadge' : 'incMapEngineBadge');
      if (badge) {
        badge.innerHTML = '<i class="bi bi-map"></i> OpenStreetMap';
        badge.style.background = '#fef3c7';
        badge.style.color = '#92400e';
      }
    }
  });
};

function setupGooglePlacesSearch(mode) {
  const isFarm = mode === 'farm';
  const input = document.getElementById(isFarm ? 'farmMapSearchInput' : 'incMapSearchInput');
  if (!input || input._placesBound || typeof google === 'undefined' || !google.maps || !google.maps.places) return;
  input._placesBound = true;
  try {
    const ac = new google.maps.places.Autocomplete(input, {
      componentRestrictions: { country: 'ph' },
      bounds: new google.maps.LatLngBounds(
        new google.maps.LatLng(DISTRICT_BOUNDS.minLat, DISTRICT_BOUNDS.minLng),
        new google.maps.LatLng(DISTRICT_BOUNDS.maxLat, DISTRICT_BOUNDS.maxLng)
      ),
      strictBounds: false
    });
    ac.addListener('place_changed', () => {
      const place = ac.getPlace();
      if (place && place.geometry && place.geometry.location) {
        const loc = place.geometry.location;
        jumpPickerTo(mode, loc.lat(), loc.lng(), place.name || place.formatted_address);
        input.blur();
      }
    });
  } catch (e) {
    console.warn('Places Autocomplete initialization note:', e);
  }
}

function jumpPickerTo(mode, lat, lng, name) {
  const isFarm = mode === 'farm';
  const st = pickerState[mode];
  const coordsEl = document.getElementById(isFarm ? 'farmPickerCoords' : 'incPickerCoords');
  lat = Number(Number(lat).toFixed(6));
  lng = Number(Number(lng).toFixed(6));
  const inBounds = isWithinDistrictBounds(lat, lng);

  if (st.engine === 'google' && st.gmap && st.gmarker) {
    st.gmap.panTo({ lat, lng });
    st.gmap.setZoom(16);
    st.gmarker.setPosition({ lat, lng });
    st.dragged = true;
  } else if (st.map && st.marker) {
    st.map.setView([lat, lng], 16);
    st.marker.setLatLng([lat, lng]);
    st.dragged = true;
  }
  if (coordsEl) {
    coordsEl.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}` + (name ? ` (${name})` : '') + (inBounds ? '' : ' (Outside 1st District!)');
    coordsEl.style.color = inBounds ? '#065f46' : '#dc2626';
  }
}

function searchPickerLocation(mode) {
  const isFarm = mode === 'farm';
  const input = document.getElementById(isFarm ? 'farmMapSearchInput' : 'incMapSearchInput');
  if (!input || !input.value.trim()) return;
  input.blur();
  const query = input.value.trim();
  const st = pickerState[mode];

  const handleFound = (lat, lng, name) => {
    lat = Number(Number(lat).toFixed(6));
    lng = Number(Number(lng).toFixed(6));
    jumpPickerTo(mode, lat, lng, name);
    if (!isWithinDistrictBounds(lat, lng)) {
      showToast(`Location "${name || query}" is outside Camarines Sur 1st District.`, 'warn');
    }
  };

  if (isGoogleMapsAvailable() && google.maps.Geocoder) {
    const geocoder = new google.maps.Geocoder();
    const fullQuery = query.toLowerCase().includes('camarines') ? query : (query + ', Camarines Sur, Philippines');
    geocoder.geocode({
      address: fullQuery,
      bounds: new google.maps.LatLngBounds(
        new google.maps.LatLng(DISTRICT_BOUNDS.minLat, DISTRICT_BOUNDS.minLng),
        new google.maps.LatLng(DISTRICT_BOUNDS.maxLat, DISTRICT_BOUNDS.maxLng)
      )
    }, (results, status) => {
      if (status === 'OK' && results && results[0]) {
        const loc = results[0].geometry.location;
        handleFound(loc.lat(), loc.lng(), results[0].formatted_address);
      } else {
        // Fallback search via Nominatim
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ' Camarines Sur')}&countrycodes=ph&limit=1`)
          .then(r => r.json())
          .then(d => {
            if (d && d.length > 0) {
              handleFound(parseFloat(d[0].lat), parseFloat(d[0].lon), d[0].display_name);
            } else {
              showToast('Location not found. Try searching e.g., "Ragay" or "Sipocot".', 'warn');
            }
          })
          .catch(() => showToast('Search error. Please drag the pin on the map.', 'error'));
      }
    });
  } else {
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ' Camarines Sur')}&countrycodes=ph&limit=1`)
      .then(r => r.json())
      .then(d => {
        if (d && d.length > 0) {
          handleFound(parseFloat(d[0].lat), parseFloat(d[0].lon), d[0].display_name);
        } else {
          showToast('Location not found.', 'warn');
        }
      })
      .catch(() => showToast('Search error.', 'error'));
  }
}

function openLocationPicker(mode, initialGps = null, message = '') {
  const isFarm = mode === 'farm';
  const wrap = document.getElementById(isFarm ? 'farmMapPickerWrap' : 'incMapPickerWrap');
  const mapElId = isFarm ? 'farmPickerMap' : 'incPickerMap';
  const coordsEl = document.getElementById(isFarm ? 'farmPickerCoords' : 'incPickerCoords');
  const engineBadge = document.getElementById(isFarm ? 'farmMapEngineBadge' : 'incMapEngineBadge');
  const basemapLbl = document.getElementById(isFarm ? 'farmBasemapLbl' : 'incBasemapLbl');
  if (!wrap) return;

  wrap.classList.add('open');
  if (message) renderGpsFeedback(mode, 'info', message);

  // Smooth scroll into view on mobile
  setTimeout(() => {
    try { wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch(e){}
  }, 80);

  const st = pickerState[mode];
  const existingLat = parseFloat(document.getElementById(isFarm ? 'fLat' : 'incLat')?.value);
  const existingLng = parseFloat(document.getElementById(isFarm ? 'fLng' : 'incLng')?.value);

  let startLat = DISTRICT_BOUNDS.center[0];
  let startLng = DISTRICT_BOUNDS.center[1];
  let startZoom = 12;

  if (initialGps && isWithinDistrictBounds(initialGps.lat, initialGps.lng)) {
    startLat = Number(initialGps.lat);
    startLng = Number(initialGps.lng);
    startZoom = 16;
  } else if (isWithinDistrictBounds(existingLat, existingLng)) {
    startLat = existingLat;
    startLng = existingLng;
    startZoom = 16;
  }

  st.lastGps = initialGps;
  st.dragged = !initialGps;

  const updateCoordLabel = (latlng) => {
    if (!coordsEl) return;
    const inBounds = isWithinDistrictBounds(latlng.lat, latlng.lng);
    coordsEl.textContent = `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}` + (inBounds ? '' : ' (Outside 1st District!)');
    coordsEl.style.color = inBounds ? '#065f46' : '#dc2626';
  };

  const useGoogle = isGoogleMapsAvailable();

  if (useGoogle) {
    st.engine = 'google';
    if (engineBadge) {
      engineBadge.innerHTML = '<i class="bi bi-google"></i> Google Maps';
      engineBadge.style.background = '#e0f2fe';
      engineBadge.style.color = '#0284c7';
    }
    const mapEl = document.getElementById(mapElId);
    if (!st.gmap && mapEl) {
      st.gmap = new google.maps.Map(mapEl, {
        center: { lat: startLat, lng: startLng },
        zoom: startZoom,
        minZoom: 9,
        maxZoom: 21,
        mapTypeId: google.maps.MapTypeId.HYBRID,
        gestureHandling: 'greedy',      // Smooth 1-finger panning on mobile touchscreens
        clickableIcons: false,          // Avoid accidental POI balloons on mobile taps
        isFractionalZoomEnabled: true,  // Silky smooth pinch-zoom
        mapTypeControl: false,          // Cleanly toggled by custom button in header
        streetViewControl: false,
        fullscreenControl: false,
        rotateControl: false,
        scaleControl: true,
        zoomControl: true,
        zoomControlOptions: {
          position: google.maps.ControlPosition.RIGHT_BOTTOM
        }
      });
      st.isHybrid = true;
      if (basemapLbl) basemapLbl.textContent = 'Street';

      // 1st District boundary outline on Google Map
      st.grect = new google.maps.Rectangle({
        bounds: {
          north: DISTRICT_BOUNDS.maxLat,
          south: DISTRICT_BOUNDS.minLat,
          east: DISTRICT_BOUNDS.maxLng,
          west: DISTRICT_BOUNDS.minLng
        },
        strokeColor: '#059669',
        strokeOpacity: 0.85,
        strokeWeight: 2,
        fillColor: '#10b981',
        fillOpacity: 0.04,
        map: st.gmap,
        clickable: false
      });

      // Draggable Marker
      st.gmarker = new google.maps.Marker({
        position: { lat: startLat, lng: startLng },
        map: st.gmap,
        draggable: true,
        animation: google.maps.Animation.DROP,
        title: isFarm ? 'Farm Holding Location (Drag or tap to refine)' : 'Incident Location (Drag or tap to refine)'
      });

      st.gmarker.addListener('drag', () => {
        const pos = st.gmarker.getPosition();
        updateCoordLabel({ lat: pos.lat(), lng: pos.lng() });
      });
      st.gmarker.addListener('dragend', () => {
        st.dragged = true;
        const pos = st.gmarker.getPosition();
        updateCoordLabel({ lat: pos.lat(), lng: pos.lng() });
      });
      st.gmap.addListener('click', (ev) => {
        st.dragged = true;
        st.gmarker.setPosition(ev.latLng);
        if (st.gmarker.setAnimation) {
          st.gmarker.setAnimation(google.maps.Animation.BOUNCE);
          setTimeout(() => { if (st.gmarker) st.gmarker.setAnimation(null); }, 380);
        }
        updateCoordLabel({ lat: ev.latLng.lat(), lng: ev.latLng.lng() });
      });

      setupGooglePlacesSearch(mode);
      updateCoordLabel({ lat: startLat, lng: startLng });
    } else if (st.gmap && st.gmarker) {
      st.gmarker.setPosition({ lat: startLat, lng: startLng });
      st.gmap.panTo({ lat: startLat, lng: startLng });
      st.gmap.setZoom(startZoom);
      updateCoordLabel({ lat: startLat, lng: startLng });
    }

    if (st.gcircle) {
      st.gcircle.setMap(null);
      st.gcircle = null;
    }
    if (initialGps && Number.isFinite(Number(initialGps.acc)) && Number(initialGps.acc) > 0 && isWithinDistrictBounds(initialGps.lat, initialGps.lng)) {
      st.gcircle = new google.maps.Circle({
        center: { lat: Number(initialGps.lat), lng: Number(initialGps.lng) },
        radius: Math.min(Number(initialGps.acc), 500),
        strokeColor: '#2563eb',
        strokeOpacity: 0.85,
        strokeWeight: 1.5,
        fillColor: '#3b82f6',
        fillOpacity: 0.15,
        map: st.gmap
      });
    }

    const triggerResize = () => {
      if (st.gmap) {
        google.maps.event.trigger(st.gmap, 'resize');
        st.gmap.setCenter({ lat: startLat, lng: startLng });
      }
    };
    requestAnimationFrame(triggerResize);
    setTimeout(triggerResize, 150);
    setTimeout(triggerResize, 350);
    return;
  }

  // Fallback to Leaflet if Google Maps is not available
  if (typeof L === 'undefined') return;
  st.engine = 'leaflet';
  if (engineBadge) {
    engineBadge.innerHTML = '<i class="bi bi-map"></i> OpenStreetMap';
    engineBadge.style.background = '#f1f5f9';
    engineBadge.style.color = '#475569';
  }
  if (!st.map) {
    const bounds = L.latLngBounds(
      [DISTRICT_BOUNDS.minLat, DISTRICT_BOUNDS.minLng],
      [DISTRICT_BOUNDS.maxLat, DISTRICT_BOUNDS.maxLng]
    );
    st.map = L.map(mapElId, {
      center: [startLat, startLng],
      zoom: startZoom,
      minZoom: 10,
      maxZoom: 19,
      maxBounds: bounds.pad(0.15)
    });

    st.hybrid = L.layerGroup([
      L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, maxNativeZoom: 18, attribution: 'Esri Imagery'
      }),
      L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, maxNativeZoom: 17
      })
    ]);
    st.street = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '&copy; OpenStreetMap'
    });
    st.hybrid.addTo(st.map);
    st.isHybrid = true;
    if (basemapLbl) basemapLbl.textContent = 'Street';

    // Show district boundary outline
    L.rectangle(bounds, {
      color: '#059669', weight: 1.5, fillOpacity: 0.03, dashArray: '6, 6', interactive: false
    }).addTo(st.map);

    st.marker = L.marker([startLat, startLng], { draggable: true }).addTo(st.map);

    st.marker.on('dragend', () => {
      st.dragged = true;
      updateCoordLabel(st.marker.getLatLng());
    });
    st.map.on('click', (ev) => {
      st.dragged = true;
      st.marker.setLatLng(ev.latlng);
      updateCoordLabel(ev.latlng);
    });
    updateCoordLabel(st.marker.getLatLng());
  } else {
    st.marker.setLatLng([startLat, startLng]);
    st.map.setView([startLat, startLng], startZoom);
    updateCoordLabel(st.marker.getLatLng());
  }

  if (st.accCircle) {
    st.map.removeLayer(st.accCircle);
    st.accCircle = null;
  }
  if (initialGps && Number.isFinite(Number(initialGps.acc)) && Number(initialGps.acc) > 0 && isWithinDistrictBounds(initialGps.lat, initialGps.lng)) {
    st.accCircle = L.circle([initialGps.lat, initialGps.lng], {
      radius: Math.min(Number(initialGps.acc), 500),
      color: '#2563eb', fillColor: '#3b82f6', fillOpacity: 0.14, weight: 1.5
    }).addTo(st.map);
  }

  const triggerLeafletResize = () => { if (st.map) st.map.invalidateSize(); };
  requestAnimationFrame(triggerLeafletResize);
  setTimeout(triggerLeafletResize, 150);
  setTimeout(triggerLeafletResize, 350);
}

// Global auto-resize listener for orientation change and viewport resizing
if (!window._pickerResizeBound) {
  window._pickerResizeBound = true;
  const onScreenChange = () => {
    Object.values(pickerState).forEach(s => {
      if (s.gmap) google.maps.event.trigger(s.gmap, 'resize');
      if (s.map) s.map.invalidateSize();
    });
  };
  window.addEventListener('resize', onScreenChange);
  window.addEventListener('orientationchange', () => {
    setTimeout(onScreenChange, 250);
  });
}

function togglePickerBasemap(mode) {
  const st = pickerState[mode];
  if (!st) return;
  const lbl = document.getElementById(mode === 'farm' ? 'farmBasemapLbl' : 'incBasemapLbl');

  if (st.engine === 'google' && st.gmap) {
    const cur = st.gmap.getMapTypeId();
    const isHybrid = (cur === google.maps.MapTypeId.HYBRID || cur === google.maps.MapTypeId.SATELLITE);
    st.gmap.setMapTypeId(isHybrid ? google.maps.MapTypeId.ROADMAP : google.maps.MapTypeId.HYBRID);
    st.isHybrid = !isHybrid;
    if (lbl) lbl.textContent = isHybrid ? 'Satellite' : 'Street';
    return;
  }

  if (!st.map) return;
  if (st.isHybrid) {
    st.map.removeLayer(st.hybrid);
    st.street.addTo(st.map);
    st.isHybrid = false;
    if (lbl) lbl.textContent = 'Satellite';
  } else {
    st.map.removeLayer(st.street);
    st.hybrid.addTo(st.map);
    st.isHybrid = true;
    if (lbl) lbl.textContent = 'Street';
  }
}

async function pickerUseMyLocation(mode) {
  const coordsEl = document.getElementById(mode === 'farm' ? 'farmPickerCoords' : 'incPickerCoords');
  if (coordsEl) { coordsEl.textContent = 'Acquiring GPS...'; coordsEl.style.color = '#1e40af'; }
  try {
    const gps = await getGPS((p) => {
      if (coordsEl) coordsEl.textContent = p.status || 'Acquiring GPS...';
    });
    if (!isWithinDistrictBounds(gps.lat, gps.lng)) {
      renderGpsFeedback(mode, 'error', `Detected GPS (${gps.lat.toFixed(5)}, ${gps.lng.toFixed(5)}, +/-${gps.acc}m) is outside Camarines Sur 1st District. Drag the pin to your exact farm location.`);
      return;
    }
    const st = pickerState[mode];
    if (st.engine === 'google' && st.gmap && st.gmarker) {
      const pos = { lat: gps.lat, lng: gps.lng };
      st.gmarker.setPosition(pos);
      st.gmap.panTo(pos);
      st.gmap.setZoom(17);
      if (st.gcircle) st.gcircle.setMap(null);
      st.gcircle = new google.maps.Circle({
        center: pos,
        radius: Math.min(gps.acc, 500),
        strokeColor: '#2563eb', strokeOpacity: 0.85, strokeWeight: 1.5,
        fillColor: '#3b82f6', fillOpacity: 0.15,
        map: st.gmap
      });
      st.lastGps = gps;
      st.dragged = false;
      if (coordsEl) {
        coordsEl.textContent = `${gps.lat.toFixed(6)}, ${gps.lng.toFixed(6)} (+/-${gps.acc}m)`;
        coordsEl.style.color = '#065f46';
      }
      renderGpsFeedback(mode, gps.acc <= 50 ? '' : 'warn', gps.acc <= 50 ? '' : `GPS accuracy is +/-${gps.acc}m. Drag the pin if needed, then click Confirm Pin.`);
    } else if (st.map && st.marker) {
      st.marker.setLatLng([gps.lat, gps.lng]);
      st.map.setView([gps.lat, gps.lng], 17);
      if (st.accCircle) st.map.removeLayer(st.accCircle);
      st.accCircle = L.circle([gps.lat, gps.lng], {
        radius: Math.min(gps.acc, 500),
        color: '#2563eb', fillColor: '#3b82f6', fillOpacity: 0.14, weight: 1.5
      }).addTo(st.map);
      st.lastGps = gps;
      st.dragged = false;
      if (coordsEl) {
        coordsEl.textContent = `${gps.lat.toFixed(6)}, ${gps.lng.toFixed(6)} (+/-${gps.acc}m)`;
        coordsEl.style.color = '#065f46';
      }
      renderGpsFeedback(mode, gps.acc <= 50 ? '' : 'warn', gps.acc <= 50 ? '' : `GPS accuracy is +/-${gps.acc}m. Drag the pin if needed, then click Confirm Pin.`);
    }
  } catch (err) {
    renderGpsFeedback(mode, 'error', err.message || 'GPS acquisition failed. Drag the pin manually.');
    if (coordsEl) { coordsEl.textContent = 'Drag pin manually'; coordsEl.style.color = '#dc2626'; }
  }
}

function closeLocationPicker(mode) {
  const wrap = document.getElementById(mode === 'farm' ? 'farmMapPickerWrap' : 'incMapPickerWrap');
  if (wrap) wrap.classList.remove('open');
}

async function confirmLocationPin(mode) {
  const st = pickerState[mode];
  if (!st) return;
  let lat, lng;
  if (st.engine === 'google' && st.gmarker) {
    const pos = st.gmarker.getPosition();
    lat = Number(pos.lat().toFixed(6));
    lng = Number(pos.lng().toFixed(6));
  } else if (st.marker) {
    const ll = st.marker.getLatLng();
    lat = Number(ll.lat.toFixed(6));
    lng = Number(ll.lng.toFixed(6));
  } else {
    return;
  }

  if (!isWithinDistrictBounds(lat, lng)) {
    showToast('Selected pin is outside Camarines Sur 1st District bounds. Please place the pin inside the district boundary.', 'error');
    return;
  }
  const useGpsSource = (!st.dragged && st.lastGps && st.lastGps.source === 'gps' && st.lastGps.acc <= 200);
  const finalGps = {
    lat: lat,
    lng: lng,
    acc: useGpsSource ? st.lastGps.acc : 10,
    source: useGpsSource ? 'gps' : 'manual'
  };

  if (mode === 'farm') {
    if (rawFarmCanvas) {
      const stamped = cloneCanvas(rawFarmCanvas);
      await stampGPS(stamped, finalGps);
      await applyFarmPhoto(stamped, finalGps, { skipPicker: true });
    } else {
      document.getElementById('fLat').value = finalGps.lat;
      document.getElementById('fLng').value = finalGps.lng;
      document.getElementById('fGpsAcc').value = finalGps.acc;
      document.getElementById('fGpsSource').value = finalGps.source;
      const badge = document.getElementById('farmGpsBadge');
      badge.className = 'gps-badge show';
      document.getElementById('farmGpsText').textContent = finalGps.source === 'manual'
        ? `${finalGps.lat.toFixed(6)}, ${finalGps.lng.toFixed(6)} (Location set via Google Maps)`
        : `${finalGps.lat.toFixed(6)}, ${finalGps.lng.toFixed(6)} (+/-${finalGps.acc}m)`;
      renderGpsFeedback('farm', '', '');
      showToast('Farm location pinned successfully via Google Maps!', 'success');
      try { const addr = await reverseGeocode(finalGps.lat, finalGps.lng); const f = document.getElementById('farmAddress'); if (f && !f.value) f.value = addr; } catch (e) {}
    }
  } else {
    if (rawIncCanvas) {
      const stamped = cloneCanvas(rawIncCanvas);
      await stampGPS(stamped, finalGps);
      await applyIncPhoto(stamped, finalGps, { skipPicker: true });
    } else {
      document.getElementById('incLat').value = finalGps.lat;
      document.getElementById('incLng').value = finalGps.lng;
      document.getElementById('incGpsAcc').value = finalGps.acc;
      document.getElementById('incGpsSource').value = finalGps.source;
      const b = document.getElementById('incGpsBadge');
      if (b) { b.style.display = 'flex'; b.style.background = '#d1fae5'; b.style.color = '#065f46'; }
      const gt = document.getElementById('incGpsText');
      if (gt) gt.textContent = finalGps.source === 'manual'
        ? `${finalGps.lat.toFixed(5)}, ${finalGps.lng.toFixed(5)} (Location set via Google Maps)`
        : `${finalGps.lat.toFixed(5)}, ${finalGps.lng.toFixed(5)} (+/-${finalGps.acc}m)`;
      renderGpsFeedback('incident', '', '');
      showToast('Incident location pinned successfully!', 'success');
    }
  }
  closeLocationPicker(mode);
}

// -------------------------------- LIVE GPS CAMERA ENGINE --------------------------------
let liveCamWatchId = null;
let liveCamClockTimer = null;
let liveCamFix = null;
let liveCamLastGeoPos = null;

function stopLiveCamGps() {
  if (liveCamWatchId !== null) {
    try { navigator.geolocation.clearWatch(liveCamWatchId); } catch (e) {}
    liveCamWatchId = null;
  }
  if (liveCamClockTimer !== null) {
    clearInterval(liveCamClockTimer);
    liveCamClockTimer = null;
  }
}

function updateCamHudClock() {
  const el = document.getElementById('camHudTime');
  if (!el) return;
  el.textContent = new Date().toLocaleString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short', day: 'numeric', year: 'numeric',
    hour: 'numeric', minute: '2-digit', second: '2-digit'
  });
}

function startLiveCamGps() {
  stopLiveCamGps();
  liveCamFix = null;
  liveCamLastGeoPos = null;
  updateCamHudClock();
  liveCamClockTimer = setInterval(updateCamHudClock, 1000);

  const latEl = document.getElementById('camHudLat');
  const lonEl = document.getElementById('camHudLon');
  const metaEl = document.getElementById('camHudMeta');
  const addrEl = document.getElementById('camHudAddr');
  if (latEl) latEl.textContent = 'Lat: acquiring...';
  if (lonEl) lonEl.textContent = 'Lon: acquiring...';
  if (metaEl) metaEl.textContent = 'Alt: 0.0m | Acc: acquiring...';
  if (addrEl) addrEl.textContent = 'Waiting for GPS satellite lock...';
  setCamGpsIndicator('Acquiring live GPS...', '');

  if (!navigator.geolocation) {
    setCamGpsIndicator('GPS not supported — you can pin manually after capture', 'err');
    return;
  }

  liveCamWatchId = navigator.geolocation.watchPosition(
    async (pos) => {
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      const acc = Math.round(pos.coords.accuracy);
      const alt = Number.isFinite(pos.coords.altitude) ? pos.coords.altitude : 0.0;
      const now = Date.now();

      // Keep best recent reading (always replace if stale > 10s or accuracy improved/similar)
      if (!liveCamFix || (now - liveCamFix.ts) > 10000 || acc <= liveCamFix.acc + 10) {
        liveCamFix = { lat, lng, acc, alt, source: 'gps', ts: now, address: liveCamFix?.address || '' };
      }

      if (latEl) latEl.textContent = `Lat: ${liveCamFix.lat.toFixed(6)}°`;
      if (lonEl) lonEl.textContent = `Lon: ${liveCamFix.lng.toFixed(6)}°`;
      if (metaEl) metaEl.textContent = `Alt: ${liveCamFix.alt.toFixed(1)}m | Acc: +/-${liveCamFix.acc}m`;

      const inDist = isWithinDistrictBounds(liveCamFix.lat, liveCamFix.lng);
      if (!inDist) {
        setCamGpsIndicator(`Live GPS +/-${liveCamFix.acc}m (outside 1st District — pin manually after capture)`, 'err');
      } else if (liveCamFix.acc <= 50) {
        setCamGpsIndicator(`Live GPS locked: +/-${liveCamFix.acc}m (High Accuracy)`, 'good');
      } else if (liveCamFix.acc <= 200) {
        setCamGpsIndicator(`Live GPS: +/-${liveCamFix.acc}m (Refining...)`, '');
      } else {
        setCamGpsIndicator(`Live GPS weak: +/-${liveCamFix.acc}m (Move outdoors or pin on map)`, 'err');
      }

      // Throttled reverse geocoding for live barangay/municipality display
      const movedFar = !liveCamLastGeoPos ||
        Math.abs(liveCamLastGeoPos.lat - liveCamFix.lat) > 0.0003 ||
        Math.abs(liveCamLastGeoPos.lng - liveCamFix.lng) > 0.0003;
      if (movedFar) {
        liveCamLastGeoPos = { lat: liveCamFix.lat, lng: liveCamFix.lng };
        try {
          const addr = await reverseGeocode(liveCamFix.lat, liveCamFix.lng);
          if (liveCamFix) liveCamFix.address = addr;
          if (addrEl) addrEl.textContent = addr;
        } catch (e) {}
      }
    },
    (err) => {
      const msg = err.code === 1
        ? 'Location permission denied — pin manually after capture'
        : 'Waiting for GPS signal...';
      setCamGpsIndicator(msg, 'err');
    },
    { enableHighAccuracy: true, maximumAge: 0, timeout: 30000 }
  );
}

// -------------------------------- CAMERA --------------------------------
async function startCam() {
  stopCam();
  try {
    mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: camFacing, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
    document.getElementById('camVideo').srcObject = mediaStream;
    document.getElementById('camModal').classList.add('open');
    startLiveCamGps();
  } catch (e) {
    let msg = 'Camera access denied.';
    if (e.name === 'NotAllowedError') msg = 'Camera permission denied. Please allow camera access in browser settings.';
    else if (e.name === 'NotFoundError') msg = 'No camera found on this device.';
    else if (e.name === 'NotReadableError') msg = 'Camera is in use by another app.';
    showToast(msg, 'error');
  }
}
function stopCam() {
  stopLiveCamGps();
  if (mediaStream) { mediaStream.getTracks().forEach(t => t.stop()); mediaStream = null; }
}
function switchCam() { camFacing = camFacing === 'environment' ? 'user' : 'environment'; startCam(); }
function closeCam() { stopCam(); document.getElementById('camModal').classList.remove('open'); }
function openFarmCam() { camMode = 'farm'; document.getElementById('camTitle').textContent = 'Capture Farm Photo + GPS'; startCam(); }
function openIncCam() { camMode = 'incident'; document.getElementById('camTitle').textContent = 'Capture Incident Photo + GPS'; startCam(); }

async function capturePhoto() {
  const video = document.getElementById('camVideo');
  const capBtn = document.querySelector('#camModal .cam-btn.capture');
  if (capBtn) capBtn.disabled = true;

  // Capture video frame immediately
  let vw = video.videoWidth || 640, vh = video.videoHeight || 480;
  const maxDim = 1024;
  if (vw > maxDim || vh > maxDim) {
    const scale = Math.min(maxDim / vw, maxDim / vh);
    vw = Math.round(vw * scale);
    vh = Math.round(vh * scale);
  }
  const rawCanvas = document.createElement('canvas');
  rawCanvas.width = vw;
  rawCanvas.height = vh;
  rawCanvas.getContext('2d').drawImage(video, 0, 0, vw, vh);

  if (camMode === 'farm') rawFarmCanvas = rawCanvas;
  else rawIncCanvas = rawCanvas;

  // Use warm live GPS fix if fresh (< 12s) and accurate (<= 150m), otherwise acquire a fresh GPS lock
  currentGPS = null;
  const warmFix = (liveCamFix && (Date.now() - liveCamFix.ts) < 12000 && liveCamFix.acc <= 150) ? { ...liveCamFix } : null;
  stopLiveCamGps();

  try {
    let gps = warmFix;
    if (!gps) {
      setCamGpsIndicator('Acquiring fresh GPS... please stay still', '');
      gps = await getGPS((p) => {
        const stateCls = p.acc !== null && p.acc <= 50 ? 'good' : '';
        setCamGpsIndicator(p.status || 'Acquiring GPS...', stateCls);
      });
    }
    if (!gps.address) {
      try { gps.address = await reverseGeocode(gps.lat, gps.lng); } catch (e) {}
    }
    currentGPS = gps;
    closeCam();

    // Check district bounds & accuracy gate
    const inDistrict = isWithinDistrictBounds(gps.lat, gps.lng);
    if (!inDistrict) {
      const unstamped = cloneCanvas(rawCanvas);
      if (camMode === 'farm') {
        await applyFarmPhoto(unstamped, null, { skipPicker: true });
        openLocationPicker('farm', null, `GPS fix (${gps.lat.toFixed(5)}, ${gps.lng.toFixed(5)}, +/-${gps.acc}m) is outside Camarines Sur 1st District. Drag the pin to your exact farm location and click Confirm Pin.`);
      } else {
        await applyIncPhoto(unstamped, null, { skipPicker: true });
        openLocationPicker('incident', null, `GPS fix (${gps.lat.toFixed(5)}, ${gps.lng.toFixed(5)}, +/-${gps.acc}m) is outside Camarines Sur 1st District. Drag the pin to the exact incident location and click Confirm Pin.`);
      }
      showToast('GPS location is outside Camarines Sur 1st District. Please pin manually.', 'error');
      return;
    }

    if (gps.acc > 200) {
      // > 200m: block auto-accept and require manual map pin
      const unstamped = cloneCanvas(rawCanvas);
      if (camMode === 'farm') {
        await applyFarmPhoto(unstamped, null, { skipPicker: true });
        openLocationPicker('farm', gps, `GPS accuracy is too low (+/-${gps.acc}m, likely Wi-Fi/IP). Please drag the pin to your exact farm location and click Confirm Pin.`);
      } else {
        await applyIncPhoto(unstamped, null, { skipPicker: true });
        openLocationPicker('incident', gps, `GPS accuracy is too low (+/-${gps.acc}m, likely Wi-Fi/IP). Please drag the pin to the exact incident location and click Confirm Pin.`);
      }
      showToast(`Low GPS accuracy (+/-${gps.acc}m). Please pin exact location on the map.`, 'warn');
      return;
    }

    // <= 200m and inside district bounds: stamp and apply
    const stamped = cloneCanvas(rawCanvas);
    await stampGPS(stamped, gps);
    if (camMode === 'farm') await applyFarmPhoto(stamped, gps);
    else await applyIncPhoto(stamped, gps);
  } catch (err) {
    setCamGpsIndicator(err.message || 'GPS unavailable', 'err');
    closeCam();
    const unstamped = cloneCanvas(rawCanvas);
    if (camMode === 'farm') {
      await applyFarmPhoto(unstamped, null, { skipPicker: true });
      openLocationPicker('farm', null, (err.message || 'GPS fix failed.') + ' Please drag the pin to your exact farm location and click Confirm Pin.');
    } else {
      await applyIncPhoto(unstamped, null, { skipPicker: true });
      openLocationPicker('incident', null, (err.message || 'GPS fix failed.') + ' Please drag the pin to the exact incident location and click Confirm Pin.');
    }
    showToast(err.message || 'GPS unavailable. Pin location on map.', 'error');
  } finally {
    if (capBtn) capBtn.disabled = false;
  }
}

async function applyFarmPhoto(canvas, gps, opts = {}) {
  const dest = document.getElementById('farmStampCanvas');
  dest.width = 640;
  dest.height = Math.round(canvas.height * (640 / canvas.width));
  dest.getContext('2d').drawImage(canvas, 0, 0, 640, dest.height);
  document.getElementById('farmPhotoPreview').classList.add('show');
  document.getElementById('fPhoto').value = dest.toDataURL('image/jpeg', 0.85);

  const badge = document.getElementById('farmGpsBadge');
  const textEl = document.getElementById('farmGpsText');

  if (!gps) {
    document.getElementById('fLat').value = '';
    document.getElementById('fLng').value = '';
    document.getElementById('fGpsAcc').value = '';
    document.getElementById('fGpsSource').value = '';
    badge.className = 'gps-badge show error';
    textEl.textContent = 'Location required — pin on map below';
    return;
  }

  document.getElementById('fLat').value = gps.lat;
  document.getElementById('fLng').value = gps.lng;
  document.getElementById('fGpsAcc').value = gps.acc ?? '';
  document.getElementById('fGpsSource').value = gps.source || 'gps';

  if (gps.source === 'manual') {
    badge.className = 'gps-badge show';
    textEl.textContent = `${Number(gps.lat).toFixed(6)}, ${Number(gps.lng).toFixed(6)} (Location set manually)`;
    renderGpsFeedback('farm', '', '');
    showToast('Farm photo & pinned location saved!', 'success');
  } else if (gps.acc <= 50) {
    badge.className = 'gps-badge show';
    textEl.textContent = `${Number(gps.lat).toFixed(6)}, ${Number(gps.lng).toFixed(6)} (+/-${gps.acc}m) [High Accuracy]`;
    renderGpsFeedback('farm', '', '');
    showToast('Photo & high-accuracy GPS captured!', 'success');
  } else {
    // 51 - 200m: accept with orange warning + Refine on map button
    badge.className = 'gps-badge show warn';
    textEl.textContent = `${Number(gps.lat).toFixed(6)}, ${Number(gps.lng).toFixed(6)} (+/-${gps.acc}m) [Moderate]`;
    renderGpsFeedback('farm', 'warn', `GPS accuracy is +/-${gps.acc}m. You can submit as-is or refine the pin: <button type="button" class="gps-picker-btn" style="margin-left:.35rem;" onclick="openLocationPicker('farm', {lat:${gps.lat},lng:${gps.lng},acc:${gps.acc},source:'gps'}, 'Drag the marker to refine your exact farm location.')"><i class="bi bi-pin-map"></i> Refine on map</button>`);
    showToast(`Photo captured with moderate GPS (+/-${gps.acc}m). You can refine on map.`, 'warn');
  }

  try {
    const addr = await reverseGeocode(gps.lat, gps.lng);
    const f = document.getElementById('farmAddress');
    if (f && !f.value) f.value = addr;
  } catch (e) {}
}

async function applyIncPhoto(canvas, gps, opts = {}) {
  let c = canvas;
  const maxDim = 1024;
  if (canvas.width > maxDim || canvas.height > maxDim) {
    const scale = Math.min(maxDim / canvas.width, maxDim / canvas.height);
    c = document.createElement('canvas');
    c.width = Math.round(canvas.width * scale);
    c.height = Math.round(canvas.height * scale);
    c.getContext('2d').drawImage(canvas, 0, 0, c.width, c.height);
  }
  const dataUrl = c.toDataURL('image/jpeg', 0.82);
  const incPhotoInput = document.getElementById('incPhoto');
  if (incPhotoInput) incPhotoInput.value = dataUrl;
  const incPrev = document.getElementById('incPhotoPreview');
  if (incPrev) {
    const thumb = document.getElementById('incPhotoThumb');
    if (thumb) thumb.src = dataUrl;
    incPrev.style.display = 'flex';
  }

  const b = document.getElementById('incGpsBadge');
  const gt = document.getElementById('incGpsText');

  if (!gps) {
    const incLat = document.getElementById('incLat'); if (incLat) incLat.value = '';
    const incLng = document.getElementById('incLng'); if (incLng) incLng.value = '';
    const incAcc = document.getElementById('incGpsAcc'); if (incAcc) incAcc.value = '';
    const incSrc = document.getElementById('incGpsSource'); if (incSrc) incSrc.value = '';
    if (b) { b.style.display = 'flex'; b.style.background = '#fee2e2'; b.style.color = '#991b1b'; }
    if (gt) gt.textContent = 'Location required — pin on map below';
    return;
  }

  document.getElementById('incLat').value = gps.lat;
  document.getElementById('incLng').value = gps.lng;
  const incAcc = document.getElementById('incGpsAcc'); if (incAcc) incAcc.value = gps.acc ?? '';
  const incSrc = document.getElementById('incGpsSource'); if (incSrc) incSrc.value = gps.source || 'gps';

  if (b) b.style.display = 'flex';
  if (gps.source === 'manual') {
    if (b) { b.style.background = '#d1fae5'; b.style.color = '#065f46'; }
    if (gt) gt.textContent = `${Number(gps.lat).toFixed(5)}, ${Number(gps.lng).toFixed(5)} (Location set manually)`;
    renderGpsFeedback('incident', '', '');
    showToast('Incident photo & pinned location saved!', 'success');
  } else if (gps.acc <= 50) {
    if (b) { b.style.background = '#d1fae5'; b.style.color = '#065f46'; }
    if (gt) gt.textContent = `${Number(gps.lat).toFixed(5)}, ${Number(gps.lng).toFixed(5)} (+/-${gps.acc}m) [High Accuracy]`;
    renderGpsFeedback('incident', '', '');
    showToast('Incident photo & high-accuracy GPS captured!', 'success');
  } else {
    if (b) { b.style.background = '#fef3c7'; b.style.color = '#92400e'; }
    if (gt) gt.textContent = `${Number(gps.lat).toFixed(5)}, ${Number(gps.lng).toFixed(5)} (+/-${gps.acc}m) [Moderate]`;
    renderGpsFeedback('incident', 'warn', `GPS accuracy is +/-${gps.acc}m. You can submit as-is or refine the pin: <button type="button" class="gps-picker-btn" style="margin-left:.35rem;" onclick="openLocationPicker('incident', {lat:${gps.lat},lng:${gps.lng},acc:${gps.acc},source:'gps'}, 'Drag the marker to refine the exact incident location.')"><i class="bi bi-pin-map"></i> Refine on map</button>`);
    showToast(`Incident photo captured with moderate GPS (+/-${gps.acc}m).`, 'warn');
  }
}

async function readExifGps(file) {
  try {
    if (window.exifr && typeof window.exifr.gps === 'function') {
      const exif = await window.exifr.gps(file);
      if (exif && Number.isFinite(exif.latitude) && Number.isFinite(exif.longitude)) {
        return { lat: exif.latitude, lng: exif.longitude, acc: 15, source: 'gps' };
      }
    }
  } catch (e) {}
  return null;
}

async function handleFarmUpload(e) {
  const file = e.target.files ? e.target.files[0] : null;
  if (!file) return;
  const exifGps = await readExifGps(file);
  const img = new Image();
  img.onload = async () => {
    const maxDim = 1024;
    let w = img.width, h = img.height;
    if (w > maxDim || h > maxDim) {
      const scale = Math.min(maxDim / w, maxDim / h);
      w = Math.round(w * scale);
      h = Math.round(h * scale);
    }
    const rawCanvas = document.createElement('canvas');
    rawCanvas.width = w;
    rawCanvas.height = h;
    rawCanvas.getContext('2d').drawImage(img, 0, 0, w, h);
    rawFarmCanvas = rawCanvas;

    if (exifGps && isWithinDistrictBounds(exifGps.lat, exifGps.lng)) {
      const stamped = cloneCanvas(rawCanvas);
      await stampGPS(stamped, exifGps);
      await applyFarmPhoto(stamped, exifGps);
    } else {
      // Do NOT stamp current device GPS on uploaded photo without valid EXIF GPS
      await applyFarmPhoto(cloneCanvas(rawCanvas), null, { skipPicker: true });
      const msg = exifGps
        ? 'Uploaded photo EXIF GPS is outside Camarines Sur 1st District. Please drag the pin to the exact farm location and click Confirm Pin.'
        : 'This uploaded photo has no embedded EXIF GPS. Please drag the pin to the exact farm location and click Confirm Pin (will be labeled "Location set manually").';
      openLocationPicker('farm', null, msg);
      showToast('Please pin the exact farm location on the map.', 'warn');
    }
  };
  img.src = URL.createObjectURL(file);
  e.target.value = '';
}

async function handleIncUpload(e) {
  const file = e.target.files ? e.target.files[0] : null;
  if (!file) return;
  const exifGps = await readExifGps(file);
  const img = new Image();
  img.onload = async () => {
    const maxDim = 1024;
    let w = img.width, h = img.height;
    if (w > maxDim || h > maxDim) {
      const scale = Math.min(maxDim / w, maxDim / h);
      w = Math.round(w * scale);
      h = Math.round(h * scale);
    }
    const rawCanvas = document.createElement('canvas');
    rawCanvas.width = w;
    rawCanvas.height = h;
    rawCanvas.getContext('2d').drawImage(img, 0, 0, w, h);
    rawIncCanvas = rawCanvas;

    if (exifGps && isWithinDistrictBounds(exifGps.lat, exifGps.lng)) {
      const stamped = cloneCanvas(rawCanvas);
      await stampGPS(stamped, exifGps);
      await applyIncPhoto(stamped, exifGps);
    } else {
      await applyIncPhoto(cloneCanvas(rawCanvas), null, { skipPicker: true });
      const msg = exifGps
        ? 'Uploaded photo EXIF GPS is outside Camarines Sur 1st District. Please drag the pin to the exact incident location and click Confirm Pin.'
        : 'This uploaded photo has no embedded EXIF GPS. Please drag the pin to the exact incident location and click Confirm Pin (will be labeled "Location set manually").';
      openLocationPicker('incident', null, msg);
      showToast('Please pin the exact incident location on the map.', 'warn');
    }
  };
  img.src = URL.createObjectURL(file);
  e.target.value = '';
}

function removeFarmPhoto() {
  rawFarmCanvas = null;
  document.getElementById('farmPhotoPreview').classList.remove('show');
  const b = document.getElementById('farmGpsBadge'); if (b) b.className = 'gps-badge';
  document.getElementById('fPhoto').value = '';
  document.getElementById('fLat').value = '';
  document.getElementById('fLng').value = '';
  const a = document.getElementById('fGpsAcc'); if (a) a.value = '';
  const s = document.getElementById('fGpsSource'); if (s) s.value = '';
  renderGpsFeedback('farm', '', '');
  closeLocationPicker('farm');
}

function removeIncPhoto() {
  rawIncCanvas = null;
  const p = document.getElementById('incPhoto'); if (p) p.value = '';
  const lat = document.getElementById('incLat'); if (lat) lat.value = '';
  const lng = document.getElementById('incLng'); if (lng) lng.value = '';
  const a = document.getElementById('incGpsAcc'); if (a) a.value = '';
  const s = document.getElementById('incGpsSource'); if (s) s.value = '';
  const b = document.getElementById('incGpsBadge'); if (b) b.style.display = 'none';
  const prev = document.getElementById('incPhotoPreview'); if (prev) prev.style.display = 'none';
  const file = document.getElementById('incFileUpload'); if (file) file.value = '';
  renderGpsFeedback('incident', '', '');
  closeLocationPicker('incident');
}

// ── FARM REG HELPERS ──────────────────────────────────────────────────────────
function setAppType(t){document.getElementById('btnNew').classList.toggle('active',t==='new');document.getElementById('btnRenew').classList.toggle('active',t==='renewal');document.getElementById('appTypeVal').value=t==='new'?'New':'Renewal';}
function setOwnership(t){document.getElementById('btnGovt').classList.toggle('active',t==='government');document.getElementById('btnPriv').classList.toggle('active',t==='private');document.getElementById('ownershipVal').value=t==='government'?'Government':'Private';}
function toggleBiz(btn){document.querySelectorAll('#bizRow .biz-pill').forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.getElementById('bizTypeVal').value=btn.textContent.trim();}
function syncFarmCb(label){const cb=label.querySelector('input[type="checkbox"]');if(cb){cb.checked=!cb.checked;}label.classList.toggle('checked',label.querySelector('input')?.checked);}

// ── DYNAMIC LIVESTOCK ROWS ────────────────────────────────────────────────────
const addedAnimalTypes = new Set();
const animalEmojis = {Cattle:'🐄',Swine:'🐖',Poultry:'🐓',Goat:'🐐',Carabao:'🐃',Horse:'🐴',Sheep:'🐑',Rabbit:'🐇'};

function toggleAnimalPicker(){
  const wrap=document.getElementById('animalPickerWrap');
  const btn=document.getElementById('btnAddAnimal');
  const isOpen=wrap.classList.contains('show');
  wrap.classList.toggle('show',!isOpen);
  btn.style.display=isOpen?'flex':'none';
  // Update picker: disable already-added types
  document.querySelectorAll('.animal-pick-btn').forEach(b=>{
    const t=b.dataset.type;
    b.disabled=addedAnimalTypes.has(t);
    b.style.opacity=addedAnimalTypes.has(t)?'.35':'1';
    b.style.cursor=addedAnimalTypes.has(t)?'not-allowed':'pointer';
  });
}

function addAnimalRow(type){
  if(addedAnimalTypes.has(type)){showToast(type+' already added','warn');return;}
  addedAnimalTypes.add(type);
  const key=type.toLowerCase();
  const emoji=animalEmojis[type]||'🐾';
  const tagDefault=`TAG-${type.substring(0,3).toUpperCase()}-${String(Date.now()).slice(-4)}`;
  const row=document.createElement('div');
  row.className='lv-dynamic-row';
  row.dataset.type=type;
  row.innerHTML=`
    <button type="button" class="remove-lv-row" onclick="removeAnimalRow(this,'${type}')" title="Remove"><i class="bi bi-x-lg"></i></button>
    <div class="lv-row-top">
      <span class="lv-animal-emoji">${emoji}</span>
      <span class="lv-row-type-label">${type}</span>
      <input type="hidden" name="lv_type_${key}" value="${type}">
    </div>
    <div class="lv-row-fields">
      <div>
        <label style="font-size:.72rem;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.03em;display:block;margin-bottom:.3rem;">No. of Heads *</label>
        <input type="number" name="lv_qty_${key}" value="1" min="1" max="99999" style="width:100%;">
      </div>
      <div>
        <label style="font-size:.72rem;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.03em;display:block;margin-bottom:.3rem;">Tag ID</label>
        <input type="text" name="lv_tag_${key}" value="${tagDefault}" style="width:100%;">
      </div>
      <div class="field-full">
        <label style="font-size:.72rem;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.03em;display:block;margin-bottom:.3rem;">Breed / Production Type</label>
        <select name="lv_breed_${key}" class="form-select" style="width:100%;">
          ${getBreedOptionsHtml(type)}
        </select>
      </div>
    </div>`;
  document.getElementById('lvDynamicList').appendChild(row);
  // Hide picker, show button
  document.getElementById('animalPickerWrap').classList.remove('show');
  document.getElementById('btnAddAnimal').style.display='flex';
}

function removeAnimalRow(btn,type){
  const row=btn.closest('.lv-dynamic-row');
  row.style.animation='none';row.style.opacity='0';row.style.transform='translateY(-6px)';
  setTimeout(()=>{row.remove();addedAnimalTypes.delete(type);},200);
}

// ── PHILIPPINE LIVESTOCK BREED MAPPINGS ──────────────────────────────────────
const LIVESTOCK_BREEDS = {
  'Swine': [
    'Philippine Native Pig (Baboy Damo / Tagalog / Markaduke / Q-Black)',
    'Upgraded F1 Cross (Krusador - Native x Exotic)',
    'Landrace (Pabaya / Maternal White - Drooping Ears)',
    'Large White / Yorkshire (Prolific White - Erect Ears)',
    'Duroc (Barako / Red Terminal Sire - High Marbling)',
    'Pietrain (Spotted / Brawny Meat - Ultra Lean)',
    'Hampshire (Belted Black with White Shoulder)',
    'Commercial Hybrid (PIC / Topigs Norsvin / Hypor)',
    'Lechonero Pig (Pang-Lechon Native/Cross - 20-35kg)'
  ],
  'Poultry': [
    'Broiler (Commercial White Meat - Cobb 500 / Ross 308)',
    'Layer (Brown Table Eggs - Lohmann / Hy-Line Brown)',
    'Philippine Native Chicken (Manok Tagalog / Darag / Banaba)',
    'Camarines Native Chicken (Bicol Local Native)',
    'Free-Range Colored Chicken (Sasso / Kabir)',
    'Philippine Mallard Duck (Itik / Pateros - Balut & Penoy)',
    'Muscovy Duck (Pato / Bibe - Meat)',
    'Pekin Duck (Commercial Duck Meat)',
    'Khaki Campbell Duck (Table Egg Producer)',
    'Japanese / Coturnix Quail (Pugo - Kwek-Kwek & Table Eggs)',
    'Fighting Cock / Gamefowl (Texas / Tupada / Manok Pansabong)',
    'Turkey (Pabo)'
  ],
  'Carabao': [
    'Philippine Carabao (Native Swamp Buffalo / Kalabaw)',
    'Bulgarian Murrah Buffalo (PCC High-Yield Dairy / Gatas)',
    'Upgraded Murrah x Native (50-50 Cross)',
    'Italian Mediterranean Buffalo'
  ],
  'Cattle': [
    'Philippine Native Cattle (Batangas / Ilocos Baka)',
    'American Brahman (Humped Heat & Tick Resistant Beef)',
    'Simbrah Cross (Simmental x Brahman)',
    'Brangus Cross (Brahman x Angus)',
    'Tropical Dairy Cattle (Holstein-Friesian x Sahiwal)'
  ],
  'Goat': [
    'Philippine Native Goat (Kambing Tagalog)',
    'Anglo-Nubian (Tainga-laylay / Dual Purpose Dairy & Meat)',
    'Boer (Heavy Meat Terminal Buck)',
    'Saanen (White Dairy Goat)',
    'Upgraded Cross (Native x Boer/Nubian)'
  ],
  'Sheep': [
    'Philippine Native Sheep (Tupa)',
    'Dorper (Meat Hair Sheep)',
    'Barbados Blackbelly',
    'St. Croix / Katahdin'
  ]
};

function getBreedOptionsHtml(type, selectedBreed = ''){
  const breeds = LIVESTOCK_BREEDS[type] || [];
  let html = '<option value="">Select breed / type...</option>';
  let isCustom = selectedBreed && !breeds.includes(selectedBreed) && selectedBreed !== '__custom__';

  breeds.forEach(b => {
    const sel = (b === selectedBreed) ? 'selected' : '';
    html += `<option value="${b}" ${sel}>${b}</option>`;
  });
  const otherSel = isCustom ? 'selected' : '';
  html += `<option value="__custom__" ${otherSel}>Other / Custom Breed...</option>`;
  return html;
}

function updateBreedDropdown(mode, currentBreed = ''){
  const isAdd = mode === 'add';
  const typeEl = document.getElementById(isAdd ? 'lvAddType' : 'lvEditType');
  const selEl  = document.getElementById(isAdd ? 'lvAddBreedSelect' : 'lvEditBreedSelect');
  const custEl = document.getElementById(isAdd ? 'lvAddBreedCustom' : 'lvEditBreedCustom');
  const hidEl  = document.getElementById(isAdd ? 'lvAddBreed' : 'lvEditBreed');
  
  if(!selEl || !hidEl) return;
  const type = typeEl ? typeEl.value : '';
  if(!type){
    selEl.innerHTML = '<option value="">Select animal type first...</option>';
    if(custEl) custEl.style.display = 'none';
    hidEl.value = '';
    return;
  }

  const breeds = LIVESTOCK_BREEDS[type] || [];
  selEl.innerHTML = getBreedOptionsHtml(type, currentBreed);

  const isCustom = currentBreed && !breeds.includes(currentBreed) && currentBreed !== '__custom__';
  if(isCustom){
    selEl.value = '__custom__';
    if(custEl){
      custEl.style.display = 'block';
      custEl.value = currentBreed;
    }
    hidEl.value = currentBreed;
  } else if(currentBreed && breeds.includes(currentBreed)){
    selEl.value = currentBreed;
    if(custEl) custEl.style.display = 'none';
    hidEl.value = currentBreed;
  } else {
    if(custEl) custEl.style.display = 'none';
    hidEl.value = selEl.value && selEl.value !== '__custom__' ? selEl.value : '';
  }
}

function onBreedSelectChange(mode){
  const isAdd = mode === 'add';
  const selEl  = document.getElementById(isAdd ? 'lvAddBreedSelect' : 'lvEditBreedSelect');
  const custEl = document.getElementById(isAdd ? 'lvAddBreedCustom' : 'lvEditBreedCustom');
  const hidEl  = document.getElementById(isAdd ? 'lvAddBreed' : 'lvEditBreed');

  if(!selEl || !hidEl) return;
  if(selEl.value === '__custom__'){
    if(custEl){
      custEl.style.display = 'block';
      custEl.focus();
      hidEl.value = custEl.value.trim();
    }
  } else {
    if(custEl){
      custEl.style.display = 'none';
    }
    hidEl.value = selEl.value;
  }
}

function syncBreedFinal(mode){
  const isAdd = mode === 'add';
  const custEl = document.getElementById(isAdd ? 'lvAddBreedCustom' : 'lvEditBreedCustom');
  const hidEl  = document.getElementById(isAdd ? 'lvAddBreed' : 'lvEditBreed');
  if(custEl && hidEl){
    hidEl.value = custEl.value.trim();
  }
}

// ── AUTO-FILL & GENERATE TAG ID ──────────────────────────────────────────────
function autoFillTagId(){
  const type=document.getElementById('lvAddType').value;
  if(!type){document.getElementById('lvAddTagId').value='';return;}
  const prefix=type.substring(0,3).toUpperCase();
  const ts=String(Date.now()).slice(-4);
  document.getElementById('lvAddTagId').value=`TAG-${prefix}-${ts}`;
}
function generateRandomTagId(){
  const typeEl=document.getElementById('lvAddType');
  const type=(typeEl && typeEl.value) ? typeEl.value : 'LV';
  const prefix=type.substring(0,3).toUpperCase();
  const rand=Math.floor(1000 + Math.random()*9000);
  document.getElementById('lvAddTagId').value=`TAG-${prefix}-${rand}`;
}

// ── INCIDENT HELPERS ──────────────────────────────────────────────────────────
function updateDiseaseTags(val){document.getElementById('diseaseTags').style.display=val==='Disease'?'flex':'none';}
function toggleDTag(el,d){el.classList.toggle('active');const f=document.querySelector('[name="title"]');if(el.classList.contains('active')&&f&&!f.value.includes(d))f.value=d+(f.value?' - '+f.value:'');}
function selectSeverity(card,val){document.querySelectorAll('.severity-card').forEach(c=>c.classList.remove('active'));card.classList.add('active');document.getElementById('incSeverityVal').value=val;}
function resetIncForm(){document.getElementById('incidentForm').reset();selectSeverity(document.querySelector('.severity-card'),'Low');document.getElementById('diseaseTags').style.display='none';document.getElementById('incGpsBadge').style.display='none';document.getElementById('incPhoto').value='';document.getElementById('incLat').value='';document.getElementById('incLng').value='';}

// ── MODERN LIVESTOCK APP CONTROLLER ───────────────────────────────────────────
let currentSpeciesFilter = 'all';
let currentLvView = 'grid';

function setLvView(mode){
  currentLvView = mode;
  document.querySelectorAll('.lv-view-btn').forEach(b => b.classList.remove('active'));
  const btn = document.getElementById('lvBtn' + mode.charAt(0).toUpperCase() + mode.slice(1));
  if(btn) btn.classList.add('active');
  
  const gView = document.getElementById('lvGridView');
  const tView = document.getElementById('lvTableView');
  const fView = document.getElementById('lvFarmView');
  
  if(gView) gView.style.display = mode === 'grid' ? 'grid' : 'none';
  if(tView) tView.style.display = mode === 'table' ? 'block' : 'none';
  if(fView) fView.style.display = mode === 'farm' ? 'block' : 'none';
  
  filterLivestock();
}

function filterBySpecies(species, btn){
  currentSpeciesFilter = (species || 'all').toLowerCase();
  document.querySelectorAll('#lvSpeciesRibbon .lv-chip-btn').forEach(b => b.classList.remove('active'));
  if(btn) btn.classList.add('active');
  filterLivestock();
}

function clearLvSearch(){
  const inp = document.getElementById('lvSearchInput');
  if(inp) inp.value = '';
  const clr = document.getElementById('lvSearchClear');
  if(clr) clr.style.display = 'none';
  filterLivestock();
}

function resetLvFilters(){
  const inp = document.getElementById('lvSearchInput');
  if(inp) inp.value = '';
  const clr = document.getElementById('lvSearchClear');
  if(clr) clr.style.display = 'none';
  const farmSel = document.getElementById('lvFarmFilter');
  if(farmSel) farmSel.value = 'all';
  const healthSel = document.getElementById('lvHealthFilter');
  if(healthSel) healthSel.value = 'all';
  const allChip = document.querySelector('#lvSpeciesRibbon .lv-chip-btn[data-species="all"]');
  filterBySpecies('all', allChip);
}

function filterLivestock(){
  const searchInput = document.getElementById('lvSearchInput');
  const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
  const clearBtn = document.getElementById('lvSearchClear');
  if(clearBtn) clearBtn.style.display = query.length > 0 ? 'block' : 'none';
  
  const farmFilter = document.getElementById('lvFarmFilter') ? document.getElementById('lvFarmFilter').value : 'all';
  const healthFilter = document.getElementById('lvHealthFilter') ? document.getElementById('lvHealthFilter').value : 'all';
  
  let visibleCount = 0;
  
  // 1. Grid Cards Filter
  const cards = document.querySelectorAll('#lvGridView .lv-card');
  cards.forEach(card => {
    const tag = (card.dataset.tag || '').toLowerCase();
    const species = (card.dataset.species || '').toLowerCase();
    const breed = (card.dataset.breed || '').toLowerCase();
    const farmId = card.dataset.farmId || '';
    const farmName = (card.dataset.farmName || '').toLowerCase();
    const health = card.dataset.health || '';
    const notes = (card.dataset.notes || '').toLowerCase();
    
    const matchesSearch = !query || tag.includes(query) || species.includes(query) || breed.includes(query) || farmName.includes(query) || notes.includes(query);
    const matchesSpecies = currentSpeciesFilter === 'all' || species === currentSpeciesFilter;
    const matchesFarm = farmFilter === 'all' || farmId === farmFilter;
    const matchesHealth = healthFilter === 'all' || health === healthFilter;
    
    const show = matchesSearch && matchesSpecies && matchesFarm && matchesHealth;
    card.style.display = show ? 'flex' : 'none';
    if(show) visibleCount++;
  });
  
  // 2. Table Rows Filter
  const rows = document.querySelectorAll('#lvTableView tbody tr.lv-tbl-row');
  rows.forEach(tr => {
    const tag = (tr.dataset.tag || '').toLowerCase();
    const species = (tr.dataset.species || '').toLowerCase();
    const breed = (tr.dataset.breed || '').toLowerCase();
    const farmId = tr.dataset.farmId || '';
    const farmName = (tr.dataset.farmName || '').toLowerCase();
    const health = tr.dataset.health || '';
    const notes = (tr.dataset.notes || '').toLowerCase();
    
    const matchesSearch = !query || tag.includes(query) || species.includes(query) || breed.includes(query) || farmName.includes(query) || notes.includes(query);
    const matchesSpecies = currentSpeciesFilter === 'all' || species === currentSpeciesFilter;
    const matchesFarm = farmFilter === 'all' || farmId === farmFilter;
    const matchesHealth = healthFilter === 'all' || health === healthFilter;
    
    tr.style.display = (matchesSearch && matchesSpecies && matchesFarm && matchesHealth) ? '' : 'none';
  });

  // 3. Farm Grouped View Filter
  const farmGroups = document.querySelectorAll('#lvFarmView .lv-farm-card');
  farmGroups.forEach(group => {
    const gFarmId = group.dataset.farmId || '';
    const matchesFarm = farmFilter === 'all' || gFarmId === farmFilter;
    let groupVisibleItems = 0;
    
    const gCards = group.querySelectorAll('.lv-card');
    gCards.forEach(c => {
      const tag = (c.dataset.tag || '').toLowerCase();
      const species = (c.dataset.species || '').toLowerCase();
      const breed = (c.dataset.breed || '').toLowerCase();
      const health = c.dataset.health || '';
      const notes = (c.dataset.notes || '').toLowerCase();
      
      const matchesSearch = !query || tag.includes(query) || species.includes(query) || breed.includes(query) || notes.includes(query);
      const matchesSpecies = currentSpeciesFilter === 'all' || species === currentSpeciesFilter;
      const matchesHealth = healthFilter === 'all' || health === healthFilter;
      
      const show = matchesSearch && matchesSpecies && matchesHealth;
      c.style.display = show ? 'flex' : 'none';
      if(show) groupVisibleItems++;
    });
    
    group.style.display = (matchesFarm && (groupVisibleItems > 0 || gCards.length === 0)) ? 'block' : 'none';
  });
  
  // 4. No Results Alert
  const noRes = document.getElementById('lvNoResults');
  if(noRes) {
    noRes.style.display = visibleCount === 0 ? 'block' : 'none';
  }
}

function copyTagId(tagId, el){
  if(navigator.clipboard && navigator.clipboard.writeText){
    navigator.clipboard.writeText(tagId).then(()=>{
      showToast(`Copied Tag ID: ${tagId}`, 'success');
      if(el){
        const orig = el.innerHTML;
        el.innerHTML = '<i class="bi bi-check2"></i> Copied!';
        setTimeout(()=>{ el.innerHTML = orig; }, 1800);
      }
    }).catch(()=>{ fallbackCopy(tagId); });
  } else {
    fallbackCopy(tagId);
  }
}
function fallbackCopy(text){
  const ta = document.createElement('textarea');
  ta.value = text;
  document.body.appendChild(ta);
  ta.select();
  document.execCommand('copy');
  document.body.removeChild(ta);
  showToast(`Copied Tag ID: ${text}`, 'success');
}

function reportIssueForAnimal(farmId, type, tagId, breed){
  navTo('incident');
  const farmSel = document.querySelector('#incidentForm select[name="farm_id"]');
  if(farmSel) farmSel.value = farmId;
  const incTypeSel = document.querySelector('#incidentForm select[name="incident_type"]');
  if(incTypeSel) {
    incTypeSel.value = 'Sick';
    if(typeof updateDiseaseTags === 'function') updateDiseaseTags('Sick');
  }
  const lvTypeSel = document.querySelector('#incidentForm select[name="livestock_type"]');
  if(lvTypeSel) lvTypeSel.value = type;
  const titleInput = document.querySelector('#incidentForm input[name="title"]');
  if(titleInput) titleInput.value = `Health issue reported for ${type} (${tagId}${breed ? ' - ' + breed : ''})`;
  const sevCard = document.querySelectorAll('.severity-card');
  if(sevCard && sevCard[1]) selectSeverity(sevCard[1], 'Medium');
  showToast(`Incident draft started for ${tagId}`, 'info');
}

function exportLivestockCSV(){
  const cards = document.querySelectorAll('#lvGridView .lv-card');
  if(!cards.length){
    showToast('No livestock records to export', 'warn');
    return;
  }
  const headers = ['Tag ID', 'Species', 'Breed', 'Farm', 'Quantity', 'Age (yrs)', 'Weight (kg)', 'Health Status', 'Notes'];
  const rows = [];
  cards.forEach(c => {
    const tag = c.dataset.tagRaw || c.dataset.tag || '';
    const species = c.dataset.speciesRaw || c.dataset.species || '';
    const breed = c.dataset.breedRaw || c.dataset.breed || '';
    const farm = c.dataset.farmNameRaw || c.dataset.farmName || '';
    const qty = c.dataset.qty || '1';
    const age = c.dataset.age || '';
    const weight = c.dataset.weight || '';
    const health = c.dataset.health || '';
    const notes = c.dataset.notesRaw || c.dataset.notes || '';
    rows.push([
      `"${tag.replace(/"/g, '""')}"`,
      `"${species.replace(/"/g, '""')}"`,
      `"${breed.replace(/"/g, '""')}"`,
      `"${farm.replace(/"/g, '""')}"`,
      qty,
      `"${age}"`,
      `"${weight}"`,
      `"${health}"`,
      `"${notes.replace(/"/g, '""')}"`
    ]);
  });
  const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...rows.map(r=>r.join(','))].join('\n');
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement('a');
  link.setAttribute('href', encodedUri);
  link.setAttribute('download', `AgriTrace_Livestock_${new Date().toISOString().slice(0,10)}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  showToast('Livestock CSV exported successfully!', 'success');
}

// ── LIVESTOCK MODALS ──────────────────────────────────────────────────────────
function openEditLv(id,tagId,type,breed,qty,age,weight,health,vacc,notes){
  document.getElementById('lvEditTitle').innerHTML='<i class="bi bi-pencil-square"></i> '+tagId;
  document.getElementById('lvEditId').value=id;document.getElementById('lvEditTag').value=tagId;
  document.getElementById('lvEditType').value=type;
  updateBreedDropdown('edit', breed || '');
  document.getElementById('lvEditQty').value=qty;document.getElementById('lvEditAge').value=age||'';
  document.getElementById('lvEditWeight').value=weight||'';document.getElementById('lvEditHealth').value=health;
  document.getElementById('lvEditVacc').value=vacc;
  const noteEl = document.getElementById('lvEditNotes');
  if(noteEl) noteEl.value = notes || '';
  openModal('lvEditModal');
}
function openAddLvForFarm(farmId){
  document.getElementById('lvAddForm').reset();
  document.getElementById('lvAddFarm').value=farmId;
  document.getElementById('lvAddTagId').value='';
  updateBreedDropdown('add', '');
  openModal('lvAddModal');
}
function deleteLv(id,tagId){
  if(!confirm(`Delete livestock ${tagId}? This cannot be undone.`))return;
  const fd=new FormData();fd.append('action','delete_livestock');fd.append('livestock_id',id);
  fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){showToast(tagId+' deleted','success');setTimeout(()=>location.reload(),900);}
    else showToast(d.message||'Delete failed','error');
  }).catch(()=>showToast('Network error','error'));
}

// ── FORM SUBMISSIONS ──────────────────────────────────────────────────────────
document.getElementById('farmForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const fLatVal = document.getElementById('fLat')?.value?.trim() || '';
  const fLngVal = document.getElementById('fLng')?.value?.trim() || '';
  const hasPhoto = !!(document.getElementById('fPhoto')?.value?.trim());
  if ((fLatVal || fLngVal || hasPhoto) && !isWithinDistrictBounds(fLatVal, fLngVal)) {
    showToast('Farm location must be pinned inside Camarines Sur 1st District bounds before submitting.', 'error');
    openLocationPicker('farm', null, 'Please confirm your exact farm pin inside Camarines Sur 1st District before submitting.');
    return;
  }
  const btn=document.getElementById('farmSubmitBtn');btn.disabled=true;btn.innerHTML='<i class="bi bi-hourglass-split"></i> Submitting…';
  try{
    const fd=new FormData(this);
    const res=await fetch('',{method:'POST',body:fd});const d=await res.json();
    if(d.success){showToast(d.message||'Farm registered!','success');setTimeout(()=>location.reload(),1400);}
    else showToast(d.message||'Registration failed','error');
  }catch(err){showToast('Network error. Please try again.','error');}
  finally{btn.disabled=false;btn.innerHTML='<i class="bi bi-send-fill"></i> Submit &amp; Get Certificate';}
});

document.getElementById('incidentForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const iLatVal = document.getElementById('incLat')?.value?.trim() || '';
  const iLngVal = document.getElementById('incLng')?.value?.trim() || '';
  const hasIncPhoto = !!(document.getElementById('incPhoto')?.value?.trim());
  if ((iLatVal || iLngVal || hasIncPhoto) && !isWithinDistrictBounds(iLatVal, iLngVal)) {
    showToast('Incident location must be pinned inside Camarines Sur 1st District bounds before submitting.', 'error');
    openLocationPicker('incident', null, 'Please confirm the exact incident pin inside Camarines Sur 1st District before submitting.');
    return;
  }
  const btn=document.getElementById('incSubmitBtn');btn.disabled=true;btn.innerHTML='<i class="bi bi-hourglass-split"></i> Sending…';
  try{
    const fd=new FormData(this);
    const res=await fetch('',{method:'POST',body:fd});const d=await res.json();
    if(d.success){showToast(`Alert sent! Priority: ${d.priority}. Notified: ${d.notified}`,'success');resetIncForm();}
    else showToast(d.message||'Failed','error');
  }catch(err){showToast('Network error','error');}
  finally{btn.disabled=false;btn.innerHTML='<i class="bi bi-send-check"></i> Send Alert';}
});

document.getElementById('lvEditForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const fd=new FormData(this);fd.append('action','update_livestock');
  const res=await fetch('',{method:'POST',body:fd});const d=await res.json();
  if(d.success){showToast('Livestock updated!','success');closeModal('lvEditModal');setTimeout(()=>location.reload(),900);}
  else showToast(d.message||'Update failed','error');
});

document.getElementById('lvAddForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const fd=new FormData(this);fd.append('action','add_livestock');
  const res=await fetch('',{method:'POST',body:fd});const d=await res.json();
  if(d.success){showToast('Livestock added!','success');closeModal('lvAddModal');setTimeout(()=>location.reload(),900);}
  else showToast(d.message||'Failed','error');
});

// ── FARMER PROFILE CONTROLLER ─────────────────────────────────────────────────
function switchProfileTab(tabName){
  document.querySelectorAll('#pfTabsNav .pf-tab-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tabName);
  });
  const tabIds = {
    details: 'pfTabDetails',
    farms: 'pfTabFarms',
    security: 'pfTabSecurity',
    activity: 'pfTabActivity'
  };
  Object.keys(tabIds).forEach(k => {
    const el = document.getElementById(tabIds[k]);
    if(el) el.classList.toggle('active', k === tabName);
  });
}

function previewProfilePhoto(input){
  if(input.files && input.files[0]){
    const file = input.files[0];
    if(file.size > 5 * 1024 * 1024){
      showToast('Photo is too large (max 5MB)', 'warn');
      input.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e){
      const preview = document.getElementById('pfEditLiveAvatar');
      if(preview){
        preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;">`;
      }
    };
    reader.readAsDataURL(file);
  }
}

const farmerPwForm = document.getElementById('farmerPwForm');
if(farmerPwForm){
  farmerPwForm.addEventListener('submit', async function(e){
    e.preventDefault();
    const btn = document.getElementById('btnUpdatePw');
    if(btn){ btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating...'; }
    try{
      const fd = new FormData(this);
      const res = await fetch('', { method: 'POST', body: fd });
      const d = await res.json();
      if(d.success){
        showToast(d.message || 'Password changed successfully!', 'success');
        this.reset();
        closeModal('farmerPasswordModal');
      } else {
        showToast(d.message || 'Password change failed', 'error');
      }
    } catch(err){
      showToast('Network error while changing password', 'error');
    } finally {
      if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bi bi-key-fill"></i> Update Password'; }
    }
  });
}

document.getElementById('profileForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const btn = document.getElementById('btnSaveProfile');
  if(btn){ btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...'; }
  try {
    const fd = new FormData(this);
    const res = await fetch('', { method: 'POST', body: fd });
    const d = await res.json();
    if(d.success){
      showToast(d.message || 'Profile updated successfully!', 'success');
      closeModal('profileModal');
      setTimeout(() => location.reload(), 900);
    } else {
      showToast(d.message || 'Update failed', 'error');
    }
  } catch(err){
    showToast('Network error while saving profile', 'error');
  } finally {
    if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2"></i> Save Changes'; }
  }
});

// ── CHAT ─────────────────────────────────────────────────────────────────────
// ── CHAT ─────────────────────────────────────────────────────────────────────
const chatState = {
  currentId: null,
  currentName: '',
  currentInit: '?',
  currentRole: '',
  lastId: 0,
  poll: null,
  farmers: [],
  currentTab: 'all',
  searchQuery: '',
  pendingMedia: null
};

async function loadFarmerList() {
  try {
    const res = await fetch('../api/chat.php?action=list_farmers');
    if (!res.ok) throw new Error('Network error');
    const d = await res.json();
    if (d.success) {
      chatState.farmers = d.farmers || [];
      const countEl = document.getElementById('chatContactsCount');
      if (countEl) countEl.textContent = `${chatState.farmers.length} contact${chatState.farmers.length === 1 ? '' : 's'}`;
      applyChatFilter();
    } else {
      throw new Error(d.message || 'Server error');
    }
  } catch (e) {
    const el = document.getElementById('farmerList');
    if (el) {
      el.innerHTML = '<div style="padding:2.5rem 1rem;text-align:center;color:var(--sub);font-size:.85rem;"><i class="bi bi-exclamation-circle" style="font-size:1.8rem;color:var(--danger);display:block;margin-bottom:.5rem;"></i>Could not load contacts.<br><button type="button" class="btn btn-secondary" style="margin-top:.75rem;padding:.4rem .9rem;font-size:.8rem;" onclick="loadFarmerList()"><i class="bi bi-arrow-clockwise"></i> Retry</button></div>';
    }
  }
}

function setChatTab(tab) {
  chatState.currentTab = tab;
  document.querySelectorAll('.chat-tab-btn').forEach(b => {
    b.classList.toggle('active', b.dataset.tab === tab);
  });
  applyChatFilter();
}

function filterFarmers(q) {
  chatState.searchQuery = (q || '').trim().toLowerCase();
  applyChatFilter();
}

function applyChatFilter() {
  let list = chatState.farmers || [];
  if (chatState.currentTab === 'official') {
    list = list.filter(f => f.role === 'Agriculture Official');
  } else if (chatState.currentTab === 'farmer') {
    list = list.filter(f => f.role === 'Farmer');
  }
  if (chatState.searchQuery) {
    const q = chatState.searchQuery;
    list = list.filter(f => {
      const full = `${f.firstName || ''} ${f.lastName || ''}`.toLowerCase();
      const pos = (f.officer_position || '').toLowerCase();
      const muni = (f.officer_muni || '').toLowerCase();
      const mob = (f.mobile || '').toLowerCase();
      return full.includes(q) || pos.includes(q) || muni.includes(q) || mob.includes(q);
    });
  }
  renderFarmerList(list);
}

function formatChatTime(raw) {
  if (!raw) return '';
  const d = new Date(raw.replace(/-/g, '/'));
  if (isNaN(d.getTime())) return '';
  const now = new Date();
  const isToday = d.toDateString() === now.toDateString();
  if (isToday) {
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function renderFarmerList(farmers) {
  const list = document.getElementById('farmerList');
  if (!list) return;
  if (!farmers || !farmers.length) {
    list.innerHTML = '<div style="padding:2.5rem 1rem;text-align:center;color:var(--sub);font-size:.85rem;"><i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.4;"></i>No matching contacts found</div>';
    return;
  }
  list.innerHTML = farmers.map(f => {
    const isOfficial = f.role === 'Agriculture Official';
    const init = (((f.firstName || '?')[0] || '') + ((f.lastName || '')[0] || '')).toUpperCase();
    const name = `${f.firstName || ''} ${f.lastName || ''}`.trim();
    const roleBadge = isOfficial 
      ? '<span class="role-tag-badge official"><i class="bi bi-shield-check" style="margin-right:2px;"></i> Official</span>'
      : '<span class="role-tag-badge farmer">Farmer</span>';
    
    let subline = '';
    if (f.last_message) {
      subline = escHtml(f.last_message);
    } else if (isOfficial) {
      subline = escHtml(f.officer_position || 'Agriculture Official') + (f.officer_muni ? ` · ${escHtml(f.officer_muni)}` : '');
    } else {
      subline = 'Tap to message';
    }

    const timeStr = formatChatTime(f.last_msg_time);
    const unread = f.unread > 0 ? `<div class="unread-badge">${f.unread}</div>` : '';
    const safeName = name.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    const roleClass = isOfficial ? 'role-official' : 'role-farmer';
    const isActive = chatState.currentId === f.id ? 'active' : '';

    return `<div class="farmer-item ${isActive}" data-id="${f.id}" onclick="openChatWith(${f.id},'${safeName}','${init}','${f.role || ''}','${escHtml(f.officer_position || '')}')">
      <div class="farmer-av-wrap">
        <div class="farmer-avatar-sm ${roleClass}">${init}</div>
        <span class="online-dot"></span>
      </div>
      <div class="farmer-item-info">
        <div class="farmer-item-top">
          <div class="farmer-name-txt">${name}</div>
          ${timeStr ? `<div class="farmer-time-txt">${timeStr}</div>` : ''}
        </div>
        <div class="farmer-item-bottom">
          <div class="farmer-preview">${subline}</div>
          <div style="display:flex;align-items:center;gap:.35rem;">
            ${roleBadge}
            ${unread}
          </div>
        </div>
      </div>
    </div>`;
  }).join('');
}

function openChatWith(id, name, initials, role = '', position = '') {
  chatState.currentId = id;
  chatState.currentName = name;
  chatState.currentInit = initials;
  chatState.currentRole = role;
  chatState.lastId = 0;
  removePendingMedia();

  // Highlight in sidebar
  document.querySelectorAll('.farmer-item').forEach(el => {
    el.classList.toggle('active', parseInt(el.dataset.id) === id);
    if (parseInt(el.dataset.id) === id) {
      const b = el.querySelector('.unread-badge');
      if (b) b.remove();
    }
  });

  // Switch view from empty placeholder to active chat
  const noConv = document.getElementById('chatNoConvView');
  const activePane = document.getElementById('chatActivePane');
  if (noConv) noConv.style.display = 'none';
  if (activePane) activePane.classList.add('show');

  // Mobile drawer slide-in
  const convView = document.getElementById('chatConvView');
  if (convView) convView.classList.add('show');

  // Header data
  const av = document.getElementById('convAvatar');
  if (av) {
    av.textContent = initials;
    av.className = 'chat-hdr-avatar ' + (role === 'Agriculture Official' ? 'role-official' : '');
  }
  document.getElementById('convName').textContent = name;
  
  const roleBadge = document.getElementById('convRoleBadge');
  if (roleBadge) {
    if (role === 'Agriculture Official') {
      roleBadge.className = 'role-tag-badge official';
      roleBadge.innerHTML = `<i class="bi bi-shield-check" style="margin-right:2px;"></i> ${position || 'DA Official'}`;
      roleBadge.style.display = 'inline-flex';
    } else {
      roleBadge.className = 'role-tag-badge farmer';
      roleBadge.textContent = 'Farmer';
      roleBadge.style.display = 'inline-flex';
    }
  }

  document.getElementById('convStatus').textContent = 'Online';

  // Set welcome card in message box
  const box = document.getElementById('convMessages');
  box.innerHTML = `
    <div class="chat-welcome-banner">
      <i class="bi ${role === 'Agriculture Official' ? 'bi-shield-check' : 'bi-chat-heart-fill'}"></i>
      <h4>Connected with ${escHtml(name)}</h4>
      <p>${role === 'Agriculture Official' 
        ? 'Reach out for veterinary consultations, farm inspections, disease reporting, or GAHP certification.' 
        : 'Connect, share agricultural advice, compare livestock feed, and coordinate with peer farmers.'}</p>
    </div>
  `;

  loadMessages(true);
  clearInterval(chatState.poll);
  chatState.poll = setInterval(() => loadMessages(false), 2500);
}

function closeChatConv() {
  const convView = document.getElementById('chatConvView');
  if (convView) convView.classList.remove('show');
  clearInterval(chatState.poll);
}

async function loadMessages(scrollBottom = false) {
  if (!chatState.currentId) return;
  try {
    const res = await fetch(`../api/chat.php?action=get_messages&other_id=${chatState.currentId}&last_id=${chatState.lastId}`);
    if (!res.ok) return;
    const d = await res.json();
    if (!d.success || !d.messages || !d.messages.length) return;
    
    const box = document.getElementById('convMessages');
    const wasBottom = box.scrollHeight - box.clientHeight <= box.scrollTop + 80;
    d.messages.forEach(msg => {
      chatState.lastId = Math.max(chatState.lastId, parseInt(msg.id) || 0);
      appendMsg(msg);
    });
    if (scrollBottom || wasBottom) {
      box.scrollTop = box.scrollHeight;
    }
  } catch (e) {}
}

function appendMsg(msg) {
  const box = document.getElementById('convMessages');
  if (!box) return;
  
  // Remove starter banner if present
  const welcome = box.querySelector('.chat-welcome-banner');
  if (welcome) welcome.remove();

  const mine = parseInt(msg.sender_id) === CURRENT_USER_ID;
  const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  const div = document.createElement('div');
  div.className = 'msg-row ' + (mine ? 'mine' : 'them');

  let mediaHtml = '';
  if (msg.media_url) {
    const src = msg.media_url.startsWith('data:') || msg.media_url.startsWith('http')
      ? msg.media_url
      : ('../' + msg.media_url.replace(/^\.\.\//, ''));
    mediaHtml = `
      <div class="msg-img-wrap" style="margin-bottom:${msg.message ? '6px' : '0'};">
        <img src="${src}" alt="Attached photo" onclick="window.open(this.src,'_blank')" loading="lazy">
      </div>`;
  }

  let textHtml = '';
  if (msg.message && msg.message.trim() !== '') {
    textHtml = `<div class="msg-bubble ${mine ? 'mine' : 'them'}">${escHtml(msg.message)}</div>`;
  }

  div.innerHTML = `
    <div style="display:flex; flex-direction:column; ${mine ? 'align-items:flex-end;' : 'align-items:flex-start;'}">
      ${mediaHtml}
      ${textHtml}
      <div class="msg-meta">
        <span>${time}</span>
        ${mine ? '<i class="bi bi-check2-all" style="color:var(--acc);margin-left:2px;"></i>' : ''}
      </div>
    </div>
  `;
  box.appendChild(div);
}

function escHtml(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function triggerChatMedia() {
  const fileInput = document.getElementById('chatFileInput');
  if (fileInput) fileInput.click();
}

function handleChatMediaSelect(e) {
  const file = e.target.files ? e.target.files[0] : null;
  if (!file) return;
  if (file.size > 8 * 1024 * 1024) {
    showToast('Photo is too large (max 8MB)', 'warn');
    return;
  }
  const reader = new FileReader();
  reader.onload = function(evt) {
    chatState.pendingMedia = {
      data: evt.target.result,
      type: 'image',
      name: file.name
    };
    document.getElementById('chatMediaThumb').src = evt.target.result;
    document.getElementById('chatMediaName').textContent = file.name;
    document.getElementById('chatMediaPreviewBar').classList.add('show');
  };
  reader.readAsDataURL(file);
}

function removePendingMedia() {
  chatState.pendingMedia = null;
  const fileInput = document.getElementById('chatFileInput');
  if (fileInput) fileInput.value = '';
  const bar = document.getElementById('chatMediaPreviewBar');
  if (bar) bar.classList.remove('show');
}

function quickInsertTag(tag) {
  const input = document.getElementById('chatInput');
  if (!input) return;
  input.value = tag + input.value;
  input.focus();
  autoResize(input);
}

async function sendChatMessage() {
  const input = document.getElementById('chatInput');
  const text = input ? input.value.trim() : '';
  const media = chatState.pendingMedia;
  if (!text && !media) return;
  if (!chatState.currentId) {
    showToast('Please select a contact first', 'warn');
    return;
  }

  input.value = '';
  input.style.height = '';
  removePendingMedia();

  const payload = {
    receiver_id: chatState.currentId,
    message: text
  };
  if (media) {
    payload.media_data = media.data;
    payload.media_type = media.type;
  }

  try {
    const res = await fetch('../api/chat.php?action=send_message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const d = await res.json();
    if (d.success) {
      const now = new Date().toISOString();
      appendMsg({
        id: d.message_id || Date.now(),
        sender_id: CURRENT_USER_ID,
        message: text,
        media_url: d.media_url || (media ? media.data : null),
        created_at: now
      });
      chatState.lastId = Math.max(chatState.lastId, d.message_id || 0);
      const box = document.getElementById('convMessages');
      if (box) box.scrollTop = box.scrollHeight;
    } else {
      showToast(d.message || 'Failed to send message', 'error');
    }
  } catch (e) {
    showToast('Chat service unavailable', 'error');
  }
}


// ── RSBSA & PCIC FORM HELPERS & TAG SCANNER ──────────────────────────────────
function setRsbsaType(type) {
  document.getElementById('btnRsbsaNew')?.classList.toggle('active', type === 'New');
  document.getElementById('btnRsbsaUpdate')?.classList.toggle('active', type === 'Updating');
  const valEl = document.getElementById('rsbsaEnrollmentTypeVal');
  if (valEl) valEl.value = type;
}

function toggleRsbsaSex(btn, val) {
  document.querySelectorAll('#rsbsaSexRow .biz-pill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const el = document.getElementById('rsbsaSexVal');
  if (el) el.value = val;
}

function toggleRsbsaHhHead(btn, val) {
  document.querySelectorAll('#rsbsaHhHeadRow .biz-pill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const el = document.getElementById('rsbsaHhHeadVal');
  if (el) el.value = val;
}

function toggleRsbsaAssoc(btn, val) {
  document.querySelectorAll('#rsbsaAssocRow .biz-pill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const el = document.getElementById('rsbsaAssocVal');
  if (el) el.value = val;
}

function setPcicAppType(type) {
  document.getElementById('btnPcicNew')?.classList.toggle('active', type === 'New');
  document.getElementById('btnPcicRenew')?.classList.toggle('active', type === 'Renewal');
  const valEl = document.getElementById('pcicAppTypeVal');
  if (valEl) valEl.value = type;
}

function setPcicCategory(cat) {
  document.getElementById('btnPcicSelf')?.classList.toggle('active', cat === 'Self-Financed');
  document.getElementById('btnPcicBorrow')?.classList.toggle('active', cat === 'Borrowing');
  const valEl = document.getElementById('pcicCategoryVal');
  if (valEl) valEl.value = cat;
}

function togglePcicSex(btn, val) {
  document.querySelectorAll('#pcicSexRow .biz-pill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const el = document.getElementById('pcicSexVal');
  if (el) el.value = val;
}

function togglePcicCivil(btn, val) {
  document.querySelectorAll('#pcicCivilRow .biz-pill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const el = document.getElementById('pcicCivilVal');
  if (el) el.value = val;
}

function togglePcicFacility(btn, val) {
  document.querySelectorAll('#pcicFacilityRow .biz-pill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const el = document.getElementById('pcicFacilityVal');
  if (el) el.value = val;
}

function togglePcicSource(btn, val) {
  document.querySelectorAll('#pcicSourceRow .biz-pill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const el = document.getElementById('pcicSourceVal');
  if (el) el.value = val;
}

function printOfficialForm(type) {
  window.print();
}

async function downloadOfficialPdf(type, filename) {
  const el = (type === 'rsbsa') ? document.getElementById('rsbsaPrintArea') : document.getElementById('pcicPrintArea');
  if (!el) { window.print(); return; }
  showToast('Generating official form PDF...', 'info');
  try {
    if (typeof html2pdf !== 'undefined') {
      if (document.fonts && document.fonts.ready) await document.fonts.ready;
      await html2pdf().set({
        margin: [0.3, 0.3, 0.3, 0.3],
        filename: filename || 'Official_Form.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF: { unit: 'in', format: [8.5, 13], orientation: 'portrait' }
      }).from(el).save();
      showToast('PDF downloaded successfully!', 'success');
    } else {
      window.print();
    }
  } catch(e) {
    console.error(e);
    window.print();
  }
}

let tagScanStream = null, tagScanFacing = 'environment', tagScanTargetInput = 'lvAddTagId';

async function openTagScanner(targetId = 'lvAddTagId') {
  tagScanTargetInput = targetId;
  const modal = document.getElementById('tagScanModal');
  if (modal) modal.classList.add('open');
  await startTagScanStream();
}

function closeTagScanner() {
  const modal = document.getElementById('tagScanModal');
  if (modal) modal.classList.remove('open');
  if (tagScanStream) {
    tagScanStream.getTracks().forEach(t => t.stop());
    tagScanStream = null;
  }
}

async function switchTagScanCam() {
  tagScanFacing = (tagScanFacing === 'environment') ? 'user' : 'environment';
  await startTagScanStream();
}

async function startTagScanStream() {
  const video = document.getElementById('tagScanVideo');
  const status = document.getElementById('tagScanStatus');
  if (tagScanStream) {
    tagScanStream.getTracks().forEach(t => t.stop());
  }
  try {
    tagScanStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: tagScanFacing, width: { ideal: 1280 }, height: { ideal: 720 } }
    });
    if (video) video.srcObject = tagScanStream;
    if (status) status.textContent = 'Aim camera at physical ear tag...';
  } catch(e) {
    if (status) status.textContent = 'Camera unavailable: ' + e.message;
  }
}

function escapeHtml(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
window.escapeHtml = escapeHtml;


function handleChatKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendChatMessage();
  }
}

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}

async function pollUnread() {
  try {
    const res = await fetch(APP_BASE + '/api/chat.php?action=unread_count');
    if (!res.ok) return;
    const d = await res.json();
    const badge = document.getElementById('chatBadge');
    if (badge) {
      badge.style.display = d.count > 0 ? '' : 'none';
      badge.textContent = d.count;
    }
  } catch (e) {}
}
setInterval(pollUnread, 7000);

// -------------------------------- WEBRTC --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
let peerConn=null, localStream=null, callType='voice', callTargetId=null, callPollTimer=null, isMuted=false, isCamOff=false, incomingOffer=null, incomingCallerId=null;
const RTC_CONFIG={iceServers:[{urls:'stun:stun.l.google.com:19302'},{urls:'stun:stun1.l.google.com:19302'}]};

async function getMediaStream(type){
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    throw new Error('SECURE_CONTEXT_REQUIRED');
  }
  // Try ideal constraints first, fall back gracefully
  const constraints = type==='video'
    ? {audio:true,video:{facingMode:'user',width:{ideal:640},height:{ideal:480}}}
    : {audio:true,video:false};
  try{
    return await navigator.mediaDevices.getUserMedia(constraints);
  }catch(err){
    if(err.name==='NotAllowedError'||err.name==='PermissionDeniedError'){
      throw new Error('PERMISSION_DENIED');
    }else if(err.name==='NotFoundError'){
      // Try audio only if video failed
      if(type==='video'){
        try{return await navigator.mediaDevices.getUserMedia({audio:true,video:false});}catch(e2){throw new Error('NO_DEVICE');}
      }
      throw new Error('NO_DEVICE');
    }else if(err.name==='NotReadableError'||err.name==='AbortError'){
      throw new Error('DEVICE_BUSY');
    }
    throw new Error('UNKNOWN:'+err.message);
  }
}

function showCallPermError(msg){
  const el=document.getElementById('permErrorMsg');
  el.querySelector('div').innerHTML=`<strong>${msg}</strong><button onclick="cleanupCall()" style="display:block;margin-top:.5rem;background:var(--danger);color:#fff;border:none;padding:.3rem .75rem;border-radius:8px;font-size:.78rem;cursor:pointer;font-family:inherit;">Close</button>`;
  el.style.display='flex';
}

async function initiateCall(type){
  if(!chatState.currentId){showToast('Open a chat first','warn');return;}
  callType=type;callTargetId=chatState.currentId;

  showCallOverlay(chatState.currentName,chatState.currentInit,'Requesting permissions...',type);
  document.getElementById('permErrorMsg').style.display='none';

  try{
    localStream=await getMediaStream(type);
  }catch(err){
    const msgs={SECURE_CONTEXT_REQUIRED:'Microphone and camera access requires a secure connection (HTTPS or localhost).',PERMISSION_DENIED:'Microphone/camera access denied. Please allow permissions in your browser settings.',NO_DEVICE:'No microphone/camera found on this device.',DEVICE_BUSY:'Microphone/camera is being used by another app. Close other apps and try again.'};
    showCallPermError(msgs[err.message]||'Could not access media device: '+err.message);
    return;
  }

  setupPeerConnection(type);
  if(type==='video'&&localStream.getVideoTracks().length>0){
    const lv=document.getElementById('localVideo');lv.srcObject=localStream;lv.style.display='block';
  }

  const offer=await peerConn.createOffer();
  await peerConn.setLocalDescription(offer);
  await sendSignal(callTargetId,{type:'offer',sdp:peerConn.localDescription,callType:type,callerName:CURRENT_NAME});
  document.getElementById('callStatus').textContent='Calling...';
  startSignalPoll();
}

async function answerCall(){
  document.getElementById('incomingCallBanner').classList.remove('show');
  if(!incomingOffer||!incomingCallerId)return;
  callTargetId=incomingCallerId;

  showCallOverlay(document.getElementById('incomingCallerName').textContent,'?','Connecting...',callType);
  document.getElementById('permErrorMsg').style.display='none';

  try{
    localStream=await getMediaStream(callType);
  }catch(err){
    const msgs={SECURE_CONTEXT_REQUIRED:'Microphone and camera access requires a secure connection (HTTPS or localhost).',PERMISSION_DENIED:'Microphone/camera access denied. Please allow permissions in your browser settings.',NO_DEVICE:'No microphone/camera found on this device.',DEVICE_BUSY:'Microphone/camera is being used by another app.'};
    showCallPermError(msgs[err.message]||'Could not access media: '+err.message);
    sendSignal(incomingCallerId,{type:'reject'}).catch(()=>{});
    return;
  }

  setupPeerConnection(callType);
  if(callType==='video'&&localStream.getVideoTracks().length>0){
    const lv=document.getElementById('localVideo');lv.srcObject=localStream;lv.style.display='block';
  }

  await peerConn.setRemoteDescription(new RTCSessionDescription(incomingOffer));
  const answer=await peerConn.createAnswer();
  await peerConn.setLocalDescription(answer);
  await sendSignal(callTargetId,{type:'answer',sdp:peerConn.localDescription});
  document.getElementById('callStatus').textContent='Connected';
  startSignalPoll();
}

function setupPeerConnection(type){
  peerConn=new RTCPeerConnection(RTC_CONFIG);
  localStream.getTracks().forEach(t=>peerConn.addTrack(t,localStream));
  peerConn.onicecandidate=e=>{if(e.candidate)sendSignal(callTargetId,{type:'ice',candidate:e.candidate});};
  peerConn.ontrack=e=>{
    const rv=document.getElementById('remoteVideo');rv.srcObject=e.streams[0];rv.style.display='block';
    document.getElementById('callStatus').textContent='Connected';
  };
  peerConn.onconnectionstatechange=()=>{
    const s=peerConn?.connectionState;
    if(s==='disconnected'||s==='failed'||s==='closed')hangupCall();
  };
}

function rejectCall(){document.getElementById('incomingCallBanner').classList.remove('show');if(incomingCallerId)sendSignal(incomingCallerId,{type:'reject'});incomingOffer=null;incomingCallerId=null;}
async function hangupCall(){if(callTargetId)sendSignal(callTargetId,{type:'hangup'}).catch(()=>{});cleanupCall();}

function cleanupCall(){
  clearInterval(callPollTimer);callPollTimer=null;
  if(peerConn){peerConn.close();peerConn=null;}
  if(localStream){localStream.getTracks().forEach(t=>t.stop());localStream=null;}
  document.getElementById('callOverlay').classList.remove('show');
  const rv=document.getElementById('remoteVideo');rv.srcObject=null;rv.style.display='none';
  const lv=document.getElementById('localVideo');lv.srcObject=null;lv.style.display='none';
  document.getElementById('permErrorMsg').style.display='none';
  isMuted=false;isCamOff=false;callTargetId=null;incomingOffer=null;incomingCallerId=null;
}

function showCallOverlay(name,initials,status,type){
  document.getElementById('callRemoteAvatar').textContent=(initials||'?').substring(0,2);
  document.getElementById('callRemoteName').textContent=name;
  document.getElementById('callStatus').textContent=status;
  document.getElementById('camToggleBtn').style.display=type==='video'?'flex':'none';
  document.getElementById('callOverlay').classList.add('show');
}
function toggleMute(){isMuted=!isMuted;localStream?.getAudioTracks().forEach(t=>t.enabled=!isMuted);const btn=document.getElementById('muteBtn');btn.innerHTML=isMuted?'<i class="bi bi-mic-mute-fill"></i>':'<i class="bi bi-mic-fill"></i>';btn.classList.toggle('active',isMuted);}
function toggleCallCam(){isCamOff=!isCamOff;localStream?.getVideoTracks().forEach(t=>t.enabled=!isCamOff);const btn=document.getElementById('camToggleBtn');btn.innerHTML=isCamOff?'<i class="bi bi-camera-video-off-fill"></i>':'<i class="bi bi-camera-video-fill"></i>';btn.classList.toggle('active',isCamOff);}

async function sendSignal(toId,payload){
  const fd=new FormData();fd.append('action','webrtc_signal');fd.append('to_id',toId);fd.append('payload',JSON.stringify(payload));
  try{await fetch('',{method:'POST',body:fd});}catch(e){}
}
function startSignalPoll(){if(callPollTimer)clearInterval(callPollTimer);callPollTimer=setInterval(pollSignals,1500);}

async function pollSignals(){
  try{const res=await fetch('?action=webrtc_poll');if(!res.ok)return;const d=await res.json();if(!d.success||!d.signals)return;for(const sig of d.signals){let p;try{p=JSON.parse(sig.payload);}catch(e){continue;}await handleSignal(p,parseInt(sig.sender_id));}}catch(e){}
}
setInterval(async()=>{
  if(callTargetId)return;
  try{const res=await fetch('?action=webrtc_poll');if(!res.ok)return;const d=await res.json();if(!d.success||!d.signals)return;for(const sig of d.signals){let p;try{p=JSON.parse(sig.payload);}catch(e){continue;}await handleSignal(p,parseInt(sig.sender_id));}}catch(e){}
},2000);

async function handleSignal(payload,senderId){
  if(payload.type==='offer'){
    if(callTargetId){sendSignal(senderId,{type:'busy'});return;}
    incomingOffer=payload.sdp;incomingCallerId=senderId;callType=payload.callType||'voice';
    const banner=document.getElementById('incomingCallBanner');
    document.getElementById('incomingCallerName').textContent=payload.callerName||'Incoming Call';
    document.getElementById('incomingCallType').textContent=callType==='video'?'<i class="bi bi-camera-video-fill"></i> Video Call':'<i class="bi bi-telephone-fill"></i> Voice Call';
    banner.classList.add('show');
    setTimeout(()=>{if(!callTargetId)banner.classList.remove('show');},30000);
  }else if(payload.type==='answer'){
    if(peerConn&&peerConn.signalingState!=='stable'){
      await peerConn.setRemoteDescription(new RTCSessionDescription(payload.sdp));
      document.getElementById('callStatus').textContent='Connected';
    }
  }else if(payload.type==='ice'){
    if(peerConn&&payload.candidate)try{await peerConn.addIceCandidate(new RTCIceCandidate(payload.candidate));}catch(e){}
  }else if(payload.type==='hangup'||payload.type==='reject'){
    if(payload.type==='reject')showToast('Call declined','warn');
    cleanupCall();
  }else if(payload.type==='busy'){showToast('User is busy','warn');cleanupCall();}
}

// -------------------------------- FORM PILL HELPERS ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
function toggleSex(btn,val){document.querySelectorAll('#sexRow .biz-pill').forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.getElementById('petSexVal').value=val;}
function togglePill(btn,key,val){const row=btn.closest('.biz-row');row.querySelectorAll('.biz-pill').forEach(b=>b.classList.remove('active'));btn.classList.add('active');const map={hh_head:'hhHeadVal',pwd:'pwdVal',fourps:'fourpsVal',indigenous:'indigenousVal',gov_id:'govIdVal',farmers_assoc:'farmersAssocVal'};if(map[key])document.getElementById(map[key]).value=val;}
function toggleLivelihood(btn,val){document.querySelectorAll('#livelihoodRow .biz-pill').forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.getElementById('livelihoodVal').value=val;}

// -------------------------------- NOTIFICATIONS MANAGEMENT ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
let currentNotifFilter = 'all';

function updateNotifBadges(unreadCount) {
  const topBell = document.getElementById('topbarBellBtn');
  const bellBadge = document.getElementById('topbarBellBadge');
  const drawerBadge = document.getElementById('drawerNotifBadge');
  const pillBadge = document.getElementById('notifUnreadBadgePill');
  const dashUnreadText = document.getElementById('dashNotifUnreadText');
  const filterUnreadCount = document.getElementById('notifCountUnread');

  if (unreadCount > 0) {
    if (topBell) topBell.classList.add('has-unread');
    if (bellBadge) { bellBadge.textContent = unreadCount; bellBadge.style.display = ''; }
    if (drawerBadge) { drawerBadge.textContent = unreadCount; drawerBadge.style.display = ''; }
    if (pillBadge) { pillBadge.textContent = unreadCount + ' Unread'; pillBadge.style.display = ''; }
    if (dashUnreadText) dashUnreadText.textContent = unreadCount + ' unread';
    if (filterUnreadCount) filterUnreadCount.textContent = unreadCount;
  } else {
    if (topBell) topBell.classList.remove('has-unread');
    if (bellBadge) bellBadge.style.display = 'none';
    if (drawerBadge) drawerBadge.style.display = 'none';
    if (pillBadge) pillBadge.style.display = 'none';
    if (dashUnreadText) dashUnreadText.textContent = '0 unread';
    if (filterUnreadCount) filterUnreadCount.textContent = '0';
  }
}

async function markNotifRead(id, e) {
  if (e) e.stopPropagation();
  try {
    const fd = new FormData();
    fd.append('action', 'mark_notif_read');
    fd.append('notif_id', id);
    const res = await fetch('farmer.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      updateNotifBadges(data.unread_count);
      const item = document.getElementById('notif-item-' + id);
      if (item) {
        item.classList.remove('unread');
        item.classList.add('read');
        item.dataset.read = '1';
        const dot = item.querySelector('.notif-dot');
        if (dot) dot.remove();
        const readBtn = item.querySelector('.mark-read-btn');
        if (readBtn) readBtn.remove();
      }
      const dashDot = document.getElementById('dash-dot-' + id);
      if (dashDot) dashDot.remove();
    }
  } catch(err) {
    console.error('Error marking notification read:', err);
  }
}

async function markAllNotifsRead() {
  try {
    const fd = new FormData();
    fd.append('action', 'mark_all_notifs_read');
    const res = await fetch('farmer.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      updateNotifBadges(0);
      document.querySelectorAll('#notifListContainer .notif-item.unread').forEach(el => {
        el.classList.remove('unread');
        el.classList.add('read');
        el.dataset.read = '1';
        el.querySelector('.notif-dot')?.remove();
        el.querySelector('.mark-read-btn')?.remove();
      });
      document.querySelectorAll('#dashNotifFeed .d-dot').forEach(el => el.remove());
      showToast('All notifications marked as read', 'success');
    }
  } catch(err) {
    console.error('Error marking all notifications read:', err);
  }
}

async function sendTestNotification() {
  try {
    const fd = new FormData();
    fd.append('action', 'send_test_notif');
    const res = await fetch('farmer.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success && data.notif) {
      updateNotifBadges(data.unread_count);
      showNotificationToast(data.notif);
      showToast('New notification received: ' + data.notif.title, 'success');
      appendNotificationToDOM(data.notif);
      const countAll = document.getElementById('notifCountAll');
      if (countAll) countAll.textContent = parseInt(countAll.textContent || '0') + 1;
    }
  } catch(err) {
    console.error('Error sending test notification:', err);
  }
}

function filterNotifs(type) {
  currentNotifFilter = type;
  document.querySelectorAll('.notif-tab').forEach(t => {
    t.classList.toggle('active', t.dataset.filter === type);
  });
  const items = document.querySelectorAll('#notifListContainer .notif-item');
  let visibleCount = 0;
  items.forEach(item => {
    const isRead = item.dataset.read === '1';
    const notifType = item.dataset.type || '';
    let show = true;
    if (type === 'unread') {
      show = !isRead;
    } else if (type === 'alerts') {
      show = (notifType === 'CRITICAL_ALERT' || notifType === 'OUTBREAK_WARNING');
    } else if (type === 'farms') {
      show = (notifType === 'FARM_APPROVED' || notifType === 'FARM_REJECTED');
    }
    item.style.display = show ? 'flex' : 'none';
    if (show) visibleCount++;
  });

  const emptyMsg = document.getElementById('notifFilteredEmpty');
  if (emptyMsg) {
    emptyMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
  }
}

function showNotificationToast(notif) {
  let container = document.getElementById('notifToastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'notifToastContainer';
    container.className = 'notif-toast-container';
    document.body.appendChild(container);
  }
  const isAlert = notif.type === 'CRITICAL_ALERT' || notif.type === 'OUTBREAK_WARNING';
  const toast = document.createElement('div');
  toast.className = 'notif-toast' + (isAlert ? ' is-alert' : '');
  toast.innerHTML = `
    <div style="font-size:1.4rem;">${isAlert ? '<i class="bi bi-exclamation-octagon-fill"></i>' : (notif.type === 'FARM_APPROVED' ? '<i class="bi bi-check-circle-fill"></i>' : '<i class="bi bi-bell-fill"></i>')}</div>
    <div style="flex:1;min-width:0;">
      <div style="font-weight:700;font-size:.85rem;color:var(--text);">${notif.title}</div>
      <div style="font-size:.76rem;color:var(--sub);margin-top:2px;line-height:1.3;">${notif.message.substring(0, 90)}${notif.message.length > 90 ? '...' : ''}</div>
    </div>
    <button type="button" style="background:none;border:none;color:var(--sub);cursor:pointer;font-size:1rem;" onclick="this.parentElement.remove()">&times;</button>
  `;
  toast.onclick = () => {
    navTo('notifications');
    toast.remove();
  };
  container.prepend(toast);
  setTimeout(() => {
    if (toast.parentElement) {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-10px)';
      setTimeout(() => toast.remove(), 300);
    }
  }, 5000);
}

function appendNotificationToDOM(notif) {
  const container = document.getElementById('notifListContainer');
  const emptyState = document.getElementById('notifEmptyState');
  if (emptyState) emptyState.style.display = 'none';

  const isAlert = notif.type === 'CRITICAL_ALERT' || notif.type === 'OUTBREAK_WARNING';
  const icon = isAlert ? 'exclamation-triangle-fill' : (notif.type === 'FARM_APPROVED' ? 'patch-check-fill' : 'bell-fill');
  const iconBg = isAlert ? 'var(--danger)' : (notif.type === 'FARM_APPROVED' ? 'var(--acc)' : '#3b82f6');
  const badgeBg = isAlert ? 'background:#fee2e2;color:#991b1b;' : 'background:#ffedd5;color:#9a3412;';

  const div = document.createElement('div');
  div.id = 'notif-item-' + notif.id;
  div.className = 'notif-item unread' + (isAlert ? ' is-alert' : '');
  div.dataset.id = notif.id;
  div.dataset.read = '0';
  div.dataset.type = notif.type;
  div.onclick = () => markNotifRead(notif.id);

  div.innerHTML = `
    <div class="notif-icon" style="background:${iconBg};"><i class="bi bi-${icon}"></i></div>
    <div style="flex:1;min-width:0;">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
        <div style="font-weight:700;font-size:.875rem;color:var(--pri);display:flex;align-items:center;gap:6px;">
          <span>${notif.title}</span>
          <span class="notif-dot ${isAlert ? 'alert' : ''}"></span>
        </div>
        <span class="badge" style="${badgeBg}font-size:.65rem;font-weight:700;padding:.15rem .45rem;border-radius:6px;">${notif.priority || 'Medium'}</span>
      </div>
      <div style="font-size:.8rem;color:var(--text);margin-top:.3rem;line-height:1.45;word-break:break-word;">${notif.message}</div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:.5rem;gap:8px;flex-wrap:wrap;">
        <div style="font-size:.7rem;color:var(--sub);"><i class="bi bi-clock"></i> ${notif.created_at}</div>
        <div style="display:flex;align-items:center;gap:6px;">
          <button type="button" class="notif-action-btn mark-read-btn" onclick="markNotifRead(${notif.id}, event)" title="Mark as Read"><i class="bi bi-check2"></i> Mark read</button>
          <button type="button" class="notif-action-btn delete-btn" onclick="deleteNotif(${notif.id}, event)" title="Delete Notification"><i class="bi bi-trash"></i> Delete</button>
        </div>
      </div>
    </div>
  `;

  if (container) {
    container.prepend(div);
  }
}

async function deleteNotif(id, e) {
  if (e) e.stopPropagation();
  const item = document.getElementById('notif-item-' + id);
  if (item) {
    item.style.transition = 'all 0.25s cubic-bezier(0.4, 0, 0.2, 1)';
    item.style.opacity = '0';
    item.style.transform = 'translateX(24px) scale(0.96)';
    item.style.pointerEvents = 'none';
  }
  try {
    const fd = new FormData();
    fd.append('action', 'delete_notif');
    fd.append('notif_id', id);
    const res = await fetch('farmer.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      if (item) {
        setTimeout(() => {
          item.remove();
          checkEmptyNotifState(data.total_count);
        }, 220);
      }
      const dashItem = document.getElementById('dash-item-' + id);
      if (dashItem) dashItem.remove();
      const dashDot = document.getElementById('dash-dot-' + id);
      if (dashDot) dashDot.remove();

      updateNotifBadges(data.unread_count);
      const countAll = document.getElementById('notifCountAll');
      if (countAll) countAll.textContent = data.total_count;

      showToast('Notification deleted', 'info');
    } else {
      if (item) {
        item.style.opacity = '1';
        item.style.transform = '';
        item.style.pointerEvents = '';
      }
      showToast('Failed to delete notification', 'error');
    }
  } catch(err) {
    console.error('Error deleting notification:', err);
    if (item) {
      item.style.opacity = '1';
      item.style.transform = '';
      item.style.pointerEvents = '';
    }
    showToast('Failed to delete notification', 'error');
  }
}

async function clearAllNotifs() {
  const countAll = document.getElementById('notifCountAll');
  const total = parseInt(countAll ? countAll.textContent || '0' : '0');
  if (total <= 0) {
    showToast('No notifications to delete', 'warn');
    return;
  }
  if (!confirm('Are you sure you want to delete all notifications? This cannot be undone.')) {
    return;
  }
  try {
    const fd = new FormData();
    fd.append('action', 'delete_all_notifs');
    const res = await fetch('farmer.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      updateNotifBadges(0);
      const listContainer = document.getElementById('notifListContainer');
      if (listContainer) {
        listContainer.innerHTML = '<div class="empty-state" id="notifEmptyState"><i class="bi bi-bell-slash"></i><h4>No notifications yet</h4><p style="font-size:.8rem;color:var(--sub);">You will receive updates when disease alerts are issued or farm applications are processed.</p></div>';
      }
      const dashFeed = document.getElementById('dashNotifFeed');
      if (dashFeed) {
        dashFeed.innerHTML = '<div class="d-empty"><i class="bi bi-bell-slash"></i><h4>No notifications yet</h4></div>';
      }
      if (countAll) countAll.textContent = '0';
      const countUnread = document.getElementById('notifCountUnread');
      if (countUnread) countUnread.textContent = '0';
      const emptyMsg = document.getElementById('notifFilteredEmpty');
      if (emptyMsg) emptyMsg.style.display = 'none';

      showToast('All notifications deleted successfully', 'success');
    } else {
      showToast('Failed to clear notifications', 'error');
    }
  } catch(err) {
    console.error('Error clearing all notifications:', err);
    showToast('Failed to clear notifications', 'error');
  }
}

function checkEmptyNotifState(total) {
  if (total <= 0) {
    const container = document.getElementById('notifListContainer');
    if (container && !document.getElementById('notifEmptyState')) {
      container.innerHTML = '<div class="empty-state" id="notifEmptyState"><i class="bi bi-bell-slash"></i><h4>No notifications yet</h4><p style="font-size:.8rem;color:var(--sub);">You will receive updates when disease alerts are issued or farm applications are processed.</p></div>';
    }
  }
  if (typeof currentNotifFilter !== 'undefined') {
    filterNotifs(currentNotifFilter);
  }
}

let lastUnreadNotifCount = <?= (int)$unreadCount ?>;
async function pollNotifications() {
  try {
    const res = await fetch('farmer.php?action=poll_notifications');
    const data = await res.json();
    if (data.success) {
      if (data.unread_count > lastUnreadNotifCount && data.notifications && data.notifications.length > 0) {
        const latest = data.notifications[0];
        if (latest && !latest.is_read) {
          showNotificationToast(latest);
          appendNotificationToDOM(latest);
        }
      }
      lastUnreadNotifCount = data.unread_count;
      updateNotifBadges(data.unread_count);
    }
  } catch(err) {}
}
setInterval(pollNotifications, 20000);
</script>

<!-- -------------------------------- CHART.JS VENDOR LIBRARY -------------------------------- -->
<script src="<?= app_url('assets/js/vendor/chart.umd.min.js') ?>"></script>
<script>if(typeof Chart==='undefined'){document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');}</script>

<script>
// -------------------------------- LIVESTOCK MANAGEMENT VISUAL CHARTS & EXPORT DROPDOWN ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
function toggleLvExportMenu(e) {
  if (e) e.stopPropagation();
  const m = document.getElementById('lvExportMenu');
  if (m) m.classList.toggle('show');
}
document.addEventListener('click', function(e) {
  const wrap = document.getElementById('lvExportDropdownWrap');
  if (wrap && !wrap.contains(e.target)) {
    const m = document.getElementById('lvExportMenu');
    if (m) m.classList.remove('show');
  }
});

let _lvSpeciesChartInstance = null;
let _lvHealthChartInstance = null;

function initLivestockCharts() {
  if (typeof Chart === 'undefined') return;
  const spCanvas = document.getElementById('lvSpeciesChart');
  const hlCanvas = document.getElementById('lvHealthVaxChart');
  if (!spCanvas || !hlCanvas) return;

  const speciesData = <?= json_encode($speciesCounts ?? []) ?>;
  const healthData = <?= json_encode($healthCounts ?? []) ?>;
  const vaxData = <?= json_encode($vaxCounts ?? []) ?>;

  // 1. Species Doughnut Chart
  const spLabels = Object.keys(speciesData);
  const spValues = Object.values(speciesData);
  const spColors = [
    '#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899',
    '#06b6d4', '#84cc16', '#64748b'
  ];

  if (_lvSpeciesChartInstance) {
    try { _lvSpeciesChartInstance.destroy(); } catch(e){}
  }
  _lvSpeciesChartInstance = new Chart(spCanvas, {
    type: 'doughnut',
    data: {
      labels: spLabels.length ? spLabels : ['No Animals'],
      datasets: [{
        data: spValues.length ? spValues : [1],
        backgroundColor: spLabels.length ? spColors.slice(0, spLabels.length) : ['#e2e8f0'],
        borderWidth: 2,
        borderColor: '#ffffff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'right',
          labels: {
            boxWidth: 12,
            font: { family: "'Plus Jakarta Sans', sans-serif", size: 11, weight: '600' },
            padding: 10
          }
        },
        tooltip: {
          callbacks: {
            label: function(ctx) {
              const val = ctx.raw || 0;
              const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
              const pct = total > 0 ? Math.round((val / total) * 100) : 0;
              return ' ' + ctx.label + ': ' + val + ' heads (' + pct + '%)';
            }
          }
        }
      },
      cutout: '62%'
    }
  });

  // 2. Health & Vaccination Bar Chart
  const healthyCount = healthData['Healthy'] || 0;
  const sickCount = healthData['Sick'] || 0;
  const vaxComplete = vaxData['Complete'] || 0;
  const vaxPending = Math.max(0, (healthyCount + sickCount) - vaxComplete);

  if (_lvHealthChartInstance) {
    try { _lvHealthChartInstance.destroy(); } catch(e){}
  }
  _lvHealthChartInstance = new Chart(hlCanvas, {
    type: 'bar',
    data: {
      labels: ['Clinical Health', 'Vaccination Standing'],
      datasets: [
        {
          label: 'Optimal / Complete',
          data: [healthyCount, vaxComplete],
          backgroundColor: '#10b981',
          borderRadius: 8
        },
        {
          label: 'Requires Attention / Pending',
          data: [sickCount, vaxPending],
          backgroundColor: '#ef4444',
          borderRadius: 8
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
          labels: {
            boxWidth: 12,
            font: { family: "'Plus Jakarta Sans', sans-serif", size: 11, weight: '600' }
          }
        }
      },
      scales: {
        x: { grid: { display: false } },
        y: {
          beginAtZero: true,
          ticks: { precision: 0 },
          grid: { color: 'rgba(0,0,0,0.05)' }
        }
      }
    }
  });
}
window.addEventListener('load', function(){ setTimeout(initLivestockCharts, 250); });
document.addEventListener('DOMContentLoaded', function(){ setTimeout(initLivestockCharts, 250); });
</script>

<script>
// -- LIVESTOCK HEALTH HISTORY MODAL & INTERACTION ---------------------------
function openHealthHistory(livestockId, tagId, breed) {
  document.getElementById('hrLivestockId').value = livestockId;
  document.getElementById('healthModalTitle').innerHTML = '<i class="bi bi-heart-pulse-fill" style="color:#059669;"></i> Health History: ' + escapeHtml(tagId);
  document.getElementById('healthModalSubtitle').textContent = 'Breed/Species: ' + escapeHtml(breed) + ' &bull; ID #' + livestockId;
  
  const container = document.getElementById('healthTimelineContainer');
  container.innerHTML = '<div style="text-align:center;padding:1.5rem;color:#64748b;font-size:.8rem;">Loading medical records...</div>';
  
  openModal('healthHistoryModal');

  const fd = new FormData();
  fd.append('action', 'get_animal_health_history');
  fd.append('livestock_id', livestockId);

  fetch('farmer.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (!res.success) {
        container.innerHTML = '<div style="color:#ef4444;font-size:.8rem;padding:1rem;">Failed to load records: ' + escapeHtml(res.message || 'Error') + '</div>';
        return;
      }

      if (res.animal) {
        document.getElementById('hmCond').textContent = res.animal.currentHealth || 'Healthy';
        document.getElementById('hmBreed').textContent = (res.animal.animal_type || '') + ' ' + (res.animal.breed || '');
        document.getElementById('hmFarm').textContent = res.animal.farm_name || 'Farm';
      }

      const records = res.records || [];
      if (records.length === 0) {
        container.innerHTML = `
          <div style="text-align:center;padding:2rem 1rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;">
            <div style="font-size:1.8rem;color:#94a3b8;margin-bottom:.35rem;"><i class="bi bi-clipboard2-pulse"></i></div>
            <strong style="display:block;font-size:.86rem;color:#334155;">No Health Records Logged Yet</strong>
            <p style="font-size:.76rem;color:#64748b;margin-top:.2rem;">Use the form above to log vaccinations, dewormings, veterinary treatments, or routine checkups for this animal.</p>
          </div>
        `;
        document.getElementById('hmLastCheck').textContent = 'None';
        document.getElementById('hmNextCheck').textContent = 'None';
        return;
      }

      document.getElementById('hmLastCheck').textContent = records[0].checkup_date || 'Recent';
      document.getElementById('hmNextCheck').textContent = records[0].next_checkup_date || 'Not scheduled';

      let html = '';
      records.forEach(r => {
        const typeIcon = r.record_type === 'Vaccination' ? 'bi-shield-shaded' : (r.record_type === 'Deworming' ? 'bi-capsule' : (r.record_type === 'Veterinary Treatment' ? 'bi-bandaid-fill' : 'bi-clipboard-pulse'));
        const typeClr = r.record_type === 'Vaccination' ? '#2563eb' : (r.record_type === 'Deworming' ? '#7c3aed' : (r.record_type === 'Veterinary Treatment' ? '#d97706' : '#059669'));
        const condClr = r.health_condition === 'Sick' ? '#ef4444' : (r.health_condition === 'Critical' ? '#dc2626' : (r.health_condition === 'Under Treatment' ? '#d97706' : '#10b981'));

        html += `
          <div style="background:#fff;border:1px solid #e2e8f0;border-left:4px solid ${typeClr};border-radius:10px;padding:.75rem .9rem;font-size:.78rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.3rem;flex-wrap:wrap;gap:.3rem;">
              <strong style="color:#0f172a;display:flex;align-items:center;gap:.35rem;">
                <i class="bi ${typeIcon}" style="color:${typeClr};"></i> ${escapeHtml(r.record_type)}
              </strong>
              <div style="display:flex;gap:.4rem;align-items:center;">
                <span style="background:${condClr}15;color:${condClr};border:1px solid ${condClr}40;padding:.1rem .45rem;border-radius:99px;font-size:.68rem;font-weight:700;">
                  ${escapeHtml(r.health_condition)}
                </span>
                <span style="color:#64748b;font-size:.72rem;"><i class="bi bi-calendar3"></i> ${escapeHtml(r.checkup_date)}</span>
              </div>
            </div>
            ${r.medicine_administered ? `<div style="color:#334155;margin-bottom:.2rem;"><strong>Medicine / Vaccine:</strong> ${escapeHtml(r.medicine_administered)} ${r.dosage ? `(${escapeHtml(r.dosage)})` : ''}</div>` : ''}
            ${r.treatment_details ? `<div style="color:#475569;margin-bottom:.2rem;background:#f8fafc;padding:.3rem .5rem;border-radius:6px;">${escapeHtml(r.treatment_details)}</div>` : ''}
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.35rem;font-size:.72rem;color:#64748b;">
              <span><i class="bi bi-person"></i> By: ${escapeHtml(r.administered_by || 'Attending Tech')}</span>
              ${r.next_checkup_date ? `<span style="color:#059669;font-weight:600;"><i class="bi bi-bell"></i> Next: ${escapeHtml(r.next_checkup_date)}</span>` : ''}
            </div>
          </div>
        `;
      });
      container.innerHTML = html;
    })
    .catch(err => {
      container.innerHTML = '<div style="color:#ef4444;font-size:.8rem;padding:1rem;">Network error fetching records.</div>';
    });
}

function saveHealthRecord(e) {
  e.preventDefault();
  const form = e.target;
  const fd = new FormData(form);
  fd.append('action', 'add_health_record');

  fetch('farmer.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        alert('Animal health event logged successfully!');
        const lvId = document.getElementById('hrLivestockId').value;
        const tag = document.getElementById('healthModalTitle').textContent.replace('Health History: ', '').trim();
        openHealthHistory(lvId, tag, '');
        form.reset();
      } else {
        alert('Error: ' + (res.message || 'Failed to save health record.'));
      }
    })
    .catch(err => alert('Network error submitting health record.'));
}

// -- VETERINARY ASSISTANCE -----------------------------------------------------
function submitVetRequest(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSubmitVetReq');
  if (btn) btn.disabled = true;

  const fd = new FormData(e.target);
  fd.append('action', 'request_veterinary_assistance');

  fetch('farmer.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (btn) btn.disabled = false;
      if (res.success) {
        alert(res.message || 'Assistance request submitted!');
        location.reload();
      } else {
        alert('Error: ' + (res.message || 'Failed to submit request.'));
      }
    })
    .catch(err => {
      if (btn) btn.disabled = false;
      alert('Network error submitting request.');
    });
}

// -- PCIC INSURANCE TABS & SUBMISSION ------------------------------------------
function switchInsuranceTab(tab) {
  const applyWrap = document.getElementById('insFormApplyWrap');
  const deathWrap = document.getElementById('insFormDeathWrap');
  const btnApply = document.getElementById('btnTabInsApply');
  const btnDeath = document.getElementById('btnTabInsDeath');

  if (tab === 'apply') {
    if (applyWrap) applyWrap.style.display = 'block';
    if (deathWrap) deathWrap.style.display = 'none';
    if (btnApply) { btnApply.className = 'btn btn-primary'; }
    if (btnDeath) { btnDeath.className = 'btn btn-secondary'; }
  } else {
    if (applyWrap) applyWrap.style.display = 'none';
    if (deathWrap) deathWrap.style.display = 'block';
    if (btnApply) { btnApply.className = 'btn btn-secondary'; }
    if (btnDeath) { btnDeath.className = 'btn btn-primary'; }
  }
}

function submitInsuranceForm(e, type) {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'submit_insurance_request');

  fetch('farmer.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        alert(res.message || 'Insurance request submitted!');
        location.reload();
      } else {
        alert('Error: ' + (res.message || 'Submission failed.'));
      }
    })
    .catch(err => alert('Network error submitting insurance form.'));
}

// -- SLAUGHTER DECLARATION -----------------------------------------------------
function submitSlaughterForm(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'submit_slaughter_declaration');

  fetch('farmer.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        alert(res.message || 'Slaughter activity declared successfully!');
        location.reload();
      } else {
        alert('Error: ' + (res.message || 'Failed to declare slaughter.'));
      }
    })
    .catch(err => alert('Network error declaring slaughter activity.'));
}

// -- DIGITAL CERTIFICATE MANAGEMENT -------------------------------------------
function uploadDigitalCert(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'upload_digital_certificate');

  fetch('farmer.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        alert(res.message || 'Certificate uploaded successfully!');
        closeModal('uploadCertModal');
        location.reload();
      } else {
        alert('Error: ' + (res.message || 'Failed to upload certificate.'));
      }
    })
    .catch(err => alert('Network error uploading certificate.'));
}

function filterCertCards(type, btn) {
  const cards = document.querySelectorAll('.cert-item-card');
  document.querySelectorAll('#pg-ecert button.btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');

  cards.forEach(card => {
    const cardType = card.dataset.certType || '';
    if (type === 'all' || cardType === type || (type === 'Veterinary Health Certificate' && cardType === 'VHC') || (type === 'Registration' && (cardType === 'Registration' || cardType === 'Renewal'))) {
      card.style.display = 'flex';
    } else {
      card.style.display = 'none';
    }
  });
}

// =========================================================================
// UNIFIED FARMER ASSISTANCE REQUEST FORM (VETERINARY & SLAUGHTER)
// =========================================================================

function switchAssistanceTab(tab) {
  const pNew = document.getElementById('assistPaneNew');
  const pHist = document.getElementById('assistPaneHistory');
  const bNew = document.getElementById('btnTabNewAssist');
  const bHist = document.getElementById('btnTabHistoryAssist');
  if (tab === 'new') {
    if (pNew) pNew.style.display = 'block';
    if (pHist) pHist.style.display = 'none';
    if (bNew) { bNew.className = 'btn btn-primary'; }
    if (bHist) { bHist.className = 'btn btn-secondary'; }
  } else {
    if (pNew) pNew.style.display = 'none';
    if (pHist) pHist.style.display = 'block';
    if (bNew) { bNew.className = 'btn btn-secondary'; }
    if (bHist) { bHist.className = 'btn btn-primary'; }
  }
}

function setAssistanceType(type) {
  const hid = document.getElementById('hidden_assistance_type');
  if (hid) hid.value = type;

  const cardVet = document.getElementById('cardAssistVet');
  const cardSlg = document.getElementById('cardAssistSlaughter');
  const badgeVet = document.getElementById('badgeActiveVet');
  const badgeSlg = document.getElementById('badgeActiveSlaughter');
  const iconSlg = document.getElementById('iconBoxSlaughter');
  const titleSlg = document.getElementById('titleSlaughter');
  const wrapDest = document.getElementById('wrapDestinationSection');
  const wrapTrans = document.getElementById('wrapTransportSection');
  const purposeSelect = document.getElementById('assist_purpose');

  if (type === 'Slaughter Assistance') {
    if (cardVet) { cardVet.style.borderColor = '#cbd5e1'; cardVet.style.background = '#fff'; }
    if (cardSlg) { cardSlg.style.borderColor = '#db2777'; cardSlg.style.background = '#fdf2f8'; }
    if (badgeVet) badgeVet.style.display = 'none';
    if (badgeSlg) badgeSlg.style.display = 'inline-block';
    if (iconSlg) { iconSlg.style.background = '#db2777'; iconSlg.style.color = '#fff'; }
    if (titleSlg) titleSlg.style.color = '#9d174d';

    if (wrapDest) wrapDest.style.display = 'block';
    if (wrapTrans) wrapTrans.style.display = 'block';
    if (purposeSelect) purposeSelect.value = 'Slaughter';
  } else {
    // Veterinary Assistance
    if (cardVet) { cardVet.style.borderColor = '#059669'; cardVet.style.background = '#f0fdf4'; }
    if (cardSlg) { cardSlg.style.borderColor = '#cbd5e1'; cardSlg.style.background = '#fff'; }
    if (badgeVet) badgeVet.style.display = 'inline-block';
    if (badgeSlg) badgeSlg.style.display = 'none';
    if (iconSlg) { iconSlg.style.background = '#e2e8f0'; iconSlg.style.color = '#64748b'; }
    if (titleSlg) titleSlg.style.color = '#0f172a';

    if (wrapDest) wrapDest.style.display = 'none';
    if (wrapTrans) wrapTrans.style.display = 'none';
    if (purposeSelect && purposeSelect.value === 'Slaughter') purposeSelect.value = 'Personal';
  }
}

function handleAssistSpeciesChange(val) {
  const wrap = document.getElementById('assist_species_other_wrap');
  const input = document.getElementById('assist_species_other');
  if (val === 'Other') {
    if (wrap) wrap.style.display = 'block';
    if (input) input.required = true;
  } else {
    if (wrap) wrap.style.display = 'none';
    if (input) { input.required = false; input.value = ''; }
  }
}

function handleAssistSexChange(val) {
  const row = document.getElementById('mixedSexRow');
  const male = document.getElementById('assist_male_count');
  const female = document.getElementById('assist_female_count');
  const total = parseInt(document.getElementById('assist_head_count')?.value || '1', 10);
  if (val === 'Mixed') {
    if (row) row.style.display = 'block';
    if (male && female) {
      male.value = Math.floor(total / 2);
      female.value = total - male.value;
    }
  } else {
    if (row) row.style.display = 'none';
  }
}

function updateMixedSexLimits() {
  const sex = document.getElementById('assist_sex')?.value;
  if (sex === 'Mixed') {
    handleAssistSexChange('Mixed');
  }
}

function handleAssistPurposeChange(val) {
  const wrap = document.getElementById('assist_purpose_other_wrap');
  const input = document.getElementById('assist_purpose_other');
  if (val === 'Other') {
    if (wrap) wrap.style.display = 'block';
    if (input) input.required = true;
  } else {
    if (wrap) wrap.style.display = 'none';
    if (input) { input.required = false; input.value = ''; }
  }
}

function handleAssistTransOther(val) {
  const wrap = document.getElementById('assist_trans_other_wrap');
  if (wrap) wrap.style.display = (val === 'Other') ? 'block' : 'none';
}

function toggleTreatmentMedHistory(val) {
  const wrap = document.getElementById('wrapMedicationRepeater');
  if (wrap) wrap.style.display = (val === 'Yes') ? 'block' : 'none';
}

function addMedicationRow() {
  const tbody = document.getElementById('medicationRowsBody');
  if (!tbody) return;
  const tr = document.createElement('tr');
  const today = new Date().toISOString().split('T')[0];
  tr.innerHTML = `
    <td style="padding:.4rem;border:1px solid #cbd5e1;"><input type="date" class="form-input med-date" style="padding:.25rem;font-size:.76rem;" value="${today}"></td>
    <td style="padding:.4rem;border:1px solid #cbd5e1;"><input type="text" class="form-input med-name" placeholder="Medicine / Vaccine" style="padding:.25rem;font-size:.76rem;"></td>
    <td style="padding:.4rem;border:1px solid #cbd5e1;">
      <select class="form-input med-route" style="padding:.25rem;font-size:.76rem;">
        <option value="Intramuscular (IM)">Intramuscular (IM)</option>
        <option value="Subcutaneous (SC)">Subcutaneous (SC)</option>
        <option value="Oral">Oral</option>
        <option value="Topical">Topical</option>
        <option value="Intravenous (IV)">Intravenous (IV)</option>
      </select>
    </td>
    <td style="padding:.4rem;border:1px solid #cbd5e1;"><input type="text" class="form-input med-withdrawal" placeholder="Withdrawal period" style="padding:.25rem;font-size:.76rem;"></td>
    <td style="padding:.4rem;border:1px solid #cbd5e1;text-align:center;">
      <button type="button" class="btn btn-sm btn-danger" onclick="removeMedicationRow(this)" style="padding:.15rem .35rem;font-size:.7rem;"><i class="bi bi-trash"></i></button>
    </td>
  `;
  tbody.appendChild(tr);
}

function removeMedicationRow(btn) {
  const tr = btn.closest('tr');
  const tbody = document.getElementById('medicationRowsBody');
  if (tbody && tbody.querySelectorAll('tr').length > 1) {
    tr.remove();
  } else {
    alert('At least one row is required when declaring medication.');
  }
}

function compileTreatmentHistoryJson() {
  const hasTreat = document.querySelector('input[name="has_treatment"]:checked')?.value || 'No';
  const hiddenInput = document.getElementById('assist_treatment_history_json');
  if (hasTreat !== 'Yes') {
    if (hiddenInput) hiddenInput.value = '';
    return;
  }
  const rows = document.querySelectorAll('#medicationRowsBody tr');
  const list = [];
  rows.forEach(r => {
    const d = r.querySelector('.med-date')?.value || '';
    const n = r.querySelector('.med-name')?.value || '';
    const rt = r.querySelector('.med-route')?.value || '';
    const w = r.querySelector('.med-withdrawal')?.value || '';
    if (n.trim() !== '') {
      list.push({ date: d, medicine: n.trim(), route: rt, withdrawal: w.trim() });
    }
  });
  if (hiddenInput) hiddenInput.value = JSON.stringify(list);
}

let pendingAssistanceFormData = null;

function handleAssistancePreSubmit(e) {
  e.preventDefault();
  compileTreatmentHistoryJson();

  const form = document.getElementById('unifiedAssistanceForm');
  pendingAssistanceFormData = new FormData(form);

  const assistType = pendingAssistanceFormData.get('assistance_type') || 'Veterinary Assistance';
  const farmerName = pendingAssistanceFormData.get('farmer_name') || '';
  const farmerContact = pendingAssistanceFormData.get('farmer_contact') || '';
  const farmerAddr = pendingAssistanceFormData.get('farmer_address') || '';
  const fBrgy = pendingAssistanceFormData.get('farmer_barangay') || '';
  const fMuni = pendingAssistanceFormData.get('farmer_municipality') || '';
  const species = pendingAssistanceFormData.get('animal_species') === 'Other' ? (pendingAssistanceFormData.get('animal_species_other') || 'Other') : pendingAssistanceFormData.get('animal_species');
  const heads = pendingAssistanceFormData.get('head_count') || '1';
  const sex = pendingAssistanceFormData.get('sex') || 'Mixed';
  const age = pendingAssistanceFormData.get('animal_age') || '';
  const purpose = pendingAssistanceFormData.get('purpose') === 'Other' ? (pendingAssistanceFormData.get('purpose_other') || 'Other') : pendingAssistanceFormData.get('purpose');
  const estVal = parseFloat(pendingAssistanceFormData.get('estimated_value') || '0').toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
  const desc = pendingAssistanceFormData.get('request_description') || '';
  const hasTreat = pendingAssistanceFormData.get('has_treatment') || 'No';

  let reviewHtml = `
    <div style="background:#f8fafc;padding:.75rem 1rem;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:1rem;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.4rem;">
        <span style="font-size:.76rem;color:#64748b;font-weight:700;">REQUEST TYPE</span>
        <span style="background:${assistType === 'Veterinary Assistance' ? '#ecfdf5' : '#fdf2f8'};color:${assistType === 'Veterinary Assistance' ? '#065f46' : '#9d174d'};font-size:.74rem;font-weight:800;padding:.2rem .6rem;border-radius:99px;">
          ${assistType}
        </span>
      </div>
      <div style="font-size:.86rem;font-weight:800;color:#0f172a;">${farmerName} &bull; ${farmerContact}</div>
      <div style="font-size:.78rem;color:#475569;">${farmerAddr}, ${fBrgy}, ${fMuni}, Camarines Sur</div>
    </div>

    <div style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:1rem;font-size:.8rem;">
      <div style="background:#f1f5f9;padding:.45rem .75rem;font-weight:700;color:#1e293b;">Livestock Specifications (For VHC / COI)</div>
      <div style="padding:.75rem;display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
        <div><b>Species:</b> ${species}</div>
        <div><b>Number of Heads:</b> ${heads} heads</div>
        <div><b>Sex:</b> ${sex} ${sex === 'Mixed' ? `(M: ${pendingAssistanceFormData.get('male_count')||0}, F: ${pendingAssistanceFormData.get('female_count')||0})` : ''}</div>
        <div><b>Age:</b> ${age}</div>
        <div><b>Purpose:</b> ${purpose}</div>
        <div><b>Estimated Value:</b> ₱ ${estVal}</div>
      </div>
    </div>
  `;

  if (assistType === 'Slaughter Assistance') {
    const dest = pendingAssistanceFormData.get('dest_address') || `${pendingAssistanceFormData.get('dest_barangay')||''}, ${pendingAssistanceFormData.get('dest_municipality')||''}`;
    const trans = pendingAssistanceFormData.get('transport_means') === 'Other' ? (pendingAssistanceFormData.get('transport_means_other')||'Other') : pendingAssistanceFormData.get('transport_means');
    const plate = pendingAssistanceFormData.get('plate_number') || 'N/A';
    const shipper = pendingAssistanceFormData.get('shipper_name') || farmerName;
    reviewHtml += `
      <div style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:1rem;font-size:.8rem;">
        <div style="background:#fdf2f8;padding:.45rem .75rem;font-weight:700;color:#9d174d;">Destination &amp; Transportation Details (For COI)</div>
        <div style="padding:.75rem;display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
          <div style="grid-column:1/-1;"><b>Destination:</b> ${dest}</div>
          <div><b>Carrier / Means:</b> ${trans}</div>
          <div><b>Plate Number:</b> ${plate}</div>
          <div><b>Shipper:</b> ${shipper}</div>
          <div><b>Proposed Date:</b> ${pendingAssistanceFormData.get('proposed_shipment_date')||'Today'}</div>
        </div>
      </div>
    `;
  }

  reviewHtml += `
    <div style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:1rem;font-size:.8rem;">
      <div style="background:#f1f5f9;padding:.45rem .75rem;font-weight:700;color:#1e293b;">Treatment / Medication &amp; Reason</div>
      <div style="padding:.75rem;">
        <div style="margin-bottom:.4rem;"><b>Medication Declared:</b> ${hasTreat === 'Yes' ? '<span style="color:#059669;font-weight:700;">Yes (Medication records provided)</span>' : 'None (No prior medication declared)'}</div>
        <div><b>Request Description:</b><div style="background:#f8fafc;padding:.45rem;border-radius:6px;margin-top:.25rem;color:#334155;">${desc}</div></div>
      </div>
    </div>
  `;

  document.getElementById('assistanceReviewContent').innerHTML = reviewHtml;
  openModal('modalAssistanceReview');
}

async function executeAssistanceSubmit() {
  const btn = document.getElementById('btnFinalConfirmSubmit');
  if (!pendingAssistanceFormData) return;
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...'; }

  try {
    const res = await fetch('farmer.php', { method: 'POST', body: pendingAssistanceFormData });
    const r = await res.json();
    if (r.success) {
      closeModal('modalAssistanceReview');
      document.getElementById('succRefNo').textContent = r.reference_no;
      document.getElementById('succDetailsSummary').textContent = r.message;
      openModal('modalAssistanceSuccess');
    } else {
      alert('Error: ' + (r.message || 'Failed to submit assistance request.'));
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Confirm & Submit Request'; }
    }
  } catch(err) {
    alert('Network error submitting request. Please check connection.');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Confirm & Submit Request'; }
  }
}

async function viewFarmerAssistanceDetails(reqId) {
  const body = document.getElementById('farmerAssistDetailBody');
  const btnBox = document.getElementById('farmerAssistModalCertButtons');
  body.innerHTML = '<div style="text-align:center;padding:2rem;"><div class="spinner-border text-success"></div><div style="margin-top:.5rem;">Loading request details...</div></div>';
  btnBox.innerHTML = '';
  openModal('modalFarmerAssistanceDetails');

  try {
    const fd = new FormData();
    fd.append('action', 'get_farmer_assistance_details');
    fd.append('request_id', reqId);
    const res = await fetch('farmer.php', { method: 'POST', body: fd });
    const r = await res.json();
    if (!r.success || !r.request) {
      body.innerHTML = '<div style="color:#ef4444;text-align:center;">Failed to load request details.</div>';
      return;
    }

    const req = r.request;
    const certs = r.certificates || [];
    document.getElementById('farmerAssistDetailSubtitle').textContent = `Ref: ${req.reference_no} • Status: ${req.status}`;

    let treatInfo = 'No medical treatment declared.';
    if (req.has_treatment === 'Yes' && req.treatment_history) {
      try {
        const tList = JSON.parse(req.treatment_history);
        if (Array.isArray(tList) && tList.length > 0) {
          treatInfo = tList.map(t => `• <b>${t.medicine||'Med'}</b> (${t.route||'IM'}) on ${t.date||'N/A'} - Withdrawal: ${t.withdrawal||'None'}`).join('<br>');
        }
      } catch(e) {
        treatInfo = req.treatment_history;
      }
    }

    let certStatusHtml = '<span style="color:#64748b;font-size:.78rem;">No certificates issued yet by Municipal DA.</span>';
    if (certs.length > 0) {
      certStatusHtml = certs.map(c => `
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:.5rem .75rem;margin-bottom:.4rem;display:flex;align-items:center;justify-content:space-between;font-size:.78rem;">
          <div>
            <b>${c.cert_type === 'VHC' ? 'Veterinary Health Certificate' : 'Certificate of Inspection'}</b> &bull; Ctrl #: <code>${c.control_number}</code>
            <div style="font-size:.72rem;color:#065f46;">Issued on ${c.issue_date} by ${c.vet_name} (${c.municipality})</div>
          </div>
          <button type="button" class="btn btn-sm btn-primary" onclick="openCertificateModal(${req.id}, '${c.cert_type}')" style="padding:.25rem .6rem;font-size:.74rem;">
            <i class="bi bi-file-earmark-pdf-fill"></i> View &amp; Print
          </button>
        </div>
      `).join('');
    }

    body.innerHTML = `
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem;background:#f8fafc;padding:.85rem;border-radius:10px;margin-bottom:1rem;font-size:.8rem;">
        <div><b>Reference #:</b> ${req.reference_no}</div>
        <div><b>Assistance Type:</b> ${req.assistance_type}</div>
        <div><b>Current Status:</b> <span style="font-weight:800;color:#059669;">${req.status}</span></div>
        <div><b>Origin:</b> ${req.origin_barangay||''}, ${req.municipality}</div>
        <div><b>Livestock:</b> ${req.head_count} heads ${req.animal_species} (${req.sex})</div>
        <div><b>Estimated Value:</b> ₱ ${parseFloat(req.estimated_value||0).toLocaleString(undefined,{minimumFractionDigits:2})}</div>
      </div>

      <div style="margin-bottom:1rem;font-size:.8rem;">
        <strong style="display:block;color:#0f172a;margin-bottom:.25rem;">Problem Description / Statement:</strong>
        <div style="background:#fff;border:1px solid #e2e8f0;padding:.6rem .8rem;border-radius:8px;line-height:1.4;">${req.request_description || 'None'}</div>
      </div>

      <div style="margin-bottom:1rem;font-size:.8rem;">
        <strong style="display:block;color:#0f172a;margin-bottom:.25rem;">Treatment &amp; Medication History:</strong>
        <div style="background:#fff;border:1px solid #e2e8f0;padding:.6rem .8rem;border-radius:8px;line-height:1.4;">${treatInfo}</div>
      </div>

      ${req.official_remarks ? `
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.65rem .85rem;font-size:.78rem;color:#166534;margin-bottom:1rem;">
          <strong>Official DA Notes:</strong> ${req.official_remarks}
        </div>
      ` : ''}

      <div style="border-top:1px solid #e2e8f0;padding-top:.85rem;">
        <strong style="display:block;color:#0f172a;margin-bottom:.45rem;font-size:.82rem;"><i class="bi bi-patch-check-fill" style="color:#059669;"></i> Issued Official Certificates:</strong>
        ${certStatusHtml}
      </div>
    `;

    // Populate footer action buttons
    certs.forEach(c => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-sm btn-primary';
      b.style.fontSize = '.78rem';
      b.innerHTML = `<i class="bi bi-file-earmark-pdf-fill"></i> Download ${c.cert_type}`;
      b.onclick = () => openCertificateModal(req.id, c.cert_type);
      btnBox.appendChild(b);
    });

  } catch(err) {
    body.innerHTML = '<div style="color:#ef4444;text-align:center;">Network error loading details.</div>';
  }
}

async function openCertificateModal(reqId, certType) {
  const wrap = document.getElementById('certPrintDocumentWrapper');
  wrap.innerHTML = '<div style="text-align:center;padding:3rem;"><div class="spinner-border text-success"></div><div style="margin-top:.5rem;">Rendering Official Certificate...</div></div>';
  openModal('modalFarmerCertificateView');

  try {
    const fd = new FormData();
    fd.append('action', 'get_farmer_assistance_details');
    fd.append('request_id', reqId);
    const res = await fetch('farmer.php', { method: 'POST', body: fd });
    const r = await res.json();
    if (!r.success || !r.request) {
      wrap.innerHTML = '<div style="color:#ef4444;text-align:center;padding:2rem;">Could not load certificate data.</div>';
      return;
    }
    const req = r.request;
    const cert = (r.certificates || []).find(c => c.cert_type === certType) || r.certificates?.[0];
    if (!cert) {
      wrap.innerHTML = '<div style="color:#ef4444;text-align:center;padding:2rem;">Official Certificate has not been finalized yet.</div>';
      return;
    }

    farmerCurrentCert = cert;
    document.getElementById('certModalTitle').innerHTML = certType === 'VHC'
      ? '<i class="bi bi-patch-check-fill" style="color:#059669;"></i> Veterinary Health Certificate (' + certEscHtml(cert.control_number || '') + ')'
      : '<i class="bi bi-receipt-cutoff" style="color:#db2777;"></i> Certificate of Inspection (' + certEscHtml(cert.control_number || '') + ')';
    const subEl = document.getElementById('certModalSubtitle');
    if (subEl) subEl.textContent = 'Official ' + certType + ' issued to ' + (cert.owner_name || req.farmer_name || '') + ' • ' + (cert.place_of_issue || 'Municipality of Ragay, Camarines Sur');

    wrap.innerHTML = buildOfficialCertificateHtml(req, cert);
  } catch(e) {
    wrap.innerHTML = '<div style="color:#ef4444;text-align:center;padding:2rem;">Network error fetching certificate.</div>';
  }
}

const FARMER_ASSISTANCE_CERTS = <?= json_encode($myAssistanceCerts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const FARMER_COI_RAGAY_LOGO = "data:image/png;base64,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";
const FARMER_COI_BAGONG_LOGO = "data:image/png;base64,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";
let farmerCurrentCert = null;

function certEscHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function farmerFmtLongDate(iso) {
  if (!iso) return '';
  const d = new Date(iso + (String(iso).length === 10 ? 'T00:00:00' : ''));
  if (isNaN(d.getTime())) return String(iso);
  return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

function farmerFmtDayOrdinal(iso) {
  if (!iso) return { day: '', monthYear: '' };
  const d = new Date(iso + (String(iso).length === 10 ? 'T00:00:00' : ''));
  if (isNaN(d.getTime())) return { day: '', monthYear: String(iso) };
  const n = d.getDate();
  const suf = (n % 10 === 1 && n !== 11) ? 'st' : (n % 10 === 2 && n !== 12) ? 'nd' : (n % 10 === 3 && n !== 13) ? 'rd' : 'th';
  const my = d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  return { day: n + suf, monthYear: my };
}

function openFarmerDigitalCert(certId, mode) {
  const cert = (FARMER_ASSISTANCE_CERTS || []).find(c => String(c.id) === String(certId));
  if (!cert) {
    alert('Certificate not found.');
    return;
  }
  farmerCurrentCert = cert;
  const req = {
    farmer_name: cert.farmer_name || cert.owner_name || '',
    farmer_address: cert.farmer_address || cert.owner_address || '',
    farmer_barangay: cert.farmer_barangay || '',
    farmer_municipality: cert.farmer_municipality || cert.municipality || 'Ragay',
    animal_species: cert.animal_species || cert.verified_species || '',
    head_count: cert.head_count || cert.verified_heads || '',
    sex: cert.sex || cert.verified_sex || '',
    animal_age: cert.animal_age || cert.verified_age || '',
    purpose: cert.req_purpose || cert.purpose || '',
    proposed_shipment_date: cert.proposed_shipment_date || cert.shipment_date || cert.issue_date || '',
    estimated_value: cert.estimated_value || cert.value || 0,
    transport_means: cert.req_transport_means || cert.transport_means || '',
    plate_number: cert.req_plate_number || cert.plate_number || '',
    shipper_name: cert.shipper_name || cert.shipper || '',
    shipper_address: cert.req_shipper_address || cert.shipper_address || '',
    origin_municipality: cert.origin_municipality || cert.municipality || 'Ragay',
    treatment_history: cert.treatment_history || ''
  };

  const isVhc = (String(cert.cert_type || '').toUpperCase() === 'VHC');
  document.getElementById('certModalTitle').innerHTML = isVhc
    ? '<i class="bi bi-patch-check-fill" style="color:#059669;"></i> Veterinary Health Certificate (' + certEscHtml(cert.control_number || '') + ')'
    : '<i class="bi bi-receipt-cutoff" style="color:#db2777;"></i> Certificate of Inspection (' + certEscHtml(cert.control_number || '') + ')';
  const subEl = document.getElementById('certModalSubtitle');
  if (subEl) subEl.textContent = 'Official ' + (isVhc ? 'VHC' : 'COI') + ' issued to ' + (cert.owner_name || req.farmer_name || '') + ' • ' + (cert.place_of_issue || 'Municipality of Ragay, Camarines Sur');

  const wrap = document.getElementById('certPrintDocumentWrapper');
  wrap.innerHTML = buildOfficialCertificateHtml(req, cert);
  openModal('modalFarmerCertificateView');

  if (mode === 'print') {
    setTimeout(() => printOfficialCertificateDocument(), 250);
  } else if (mode === 'pdf') {
    setTimeout(() => downloadFarmerOfficialCertPdf(), 350);
  }
}

function buildOfficialCertificateHtml(req, cert) {
  const isVhc = (String(cert.cert_type || '').toUpperCase() === 'VHC');
  const muni = cert.municipality || req.origin_municipality || 'Ragay';

  if (!isVhc) {
    // EXACT OFFICIAL CERTIFICATE OF INSPECTION (COI) HTML TEMPLATE
    const inspDate = farmerFmtLongDate(cert.inspection_date || cert.issue_date);
    const issuedParts = farmerFmtDayOrdinal(cert.issue_date);
    const validUntil = farmerFmtLongDate(cert.expiry_date || cert.issue_date);
    const payDate = farmerFmtLongDate(cert.payment_date || cert.issue_date);
    const isPersonal = String(cert.purpose || req.purpose || '').toLowerCase() === 'personal';
    const isCommercial = !isPersonal;
    const valNum = parseFloat(cert.value || req.estimated_value || 0);
    const amtNum = parseFloat(cert.amount_paid || 0);

    return `
      <div id="farmerPrintableCertSheet" class="printable-cert-sheet" style="background:#fff;width:7in;max-width:100%;margin:0 auto;padding:0.55in 0.6in;box-sizing:content-box;font-family:'Calibri','Arial',sans-serif;font-size:11pt;line-height:1.4;color:#000;box-shadow:0 4px 16px rgba(0,0,0,.08);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;text-align:center;">
          ${FARMER_COI_RAGAY_LOGO ? `<img src="${FARMER_COI_RAGAY_LOGO}" alt="Municipality Seal" style="width:80px;height:80px;object-fit:contain;">` : '<div style="width:80px;"></div>'}
          <div style="flex:1;padding:0 15px;">
            <p style="margin:0;font-family:'Old English Text MT','Times New Roman',serif;font-size:11pt;">Republic of the Philippines</p>
            <p style="margin:0;font-family:'Times New Roman',Times,serif;font-size:11pt;">Province of Camarines Sur</p>
            <p style="margin:0;font-family:'Times New Roman',Times,serif;font-size:11pt;">Municipality of ${certEscHtml(muni)}</p>
            <h2 style="margin:4px 0 0 0;font-family:'Calibri','Arial',sans-serif;font-size:13pt;font-weight:bold;letter-spacing:0.5px;">OFFICE OF THE MUNICIPAL AGRICULTURIST</h2>
          </div>
          ${FARMER_COI_BAGONG_LOGO ? `<img src="${FARMER_COI_BAGONG_LOGO}" alt="Bagong Pilipinas" style="width:80px;height:80px;object-fit:contain;">` : '<div style="width:80px;"></div>'}
        </div>

        <div style="text-align:center;font-weight:bold;font-size:14pt;margin:22px 0 18px 0;letter-spacing:1px;">CERTIFICATE OF INSPECTION</div>

        <p style="margin:0 0 14px 0;">
          I hereby certify that the following animal(s) with the corresponding details had been inspected on
          <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;text-align:center;">${certEscHtml(inspDate)}</span>
          and found to be healthy in appearance.
        </p>

        <table style="width:100%;margin:22px 0;text-align:center;border-collapse:collapse;">
          <thead>
            <tr>
              <th style="width:34%;font-weight:bold;padding-bottom:12px;">Kind of Live Animal(s)</th>
              <th style="width:33%;font-weight:bold;padding-bottom:12px;">No. of Heads</th>
              <th style="width:33%;font-weight:bold;padding-bottom:12px;">Value</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="padding-top:5px;"><span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;">${certEscHtml(cert.verified_species || req.animal_species || '')}</span></td>
              <td style="padding-top:5px;"><span style="display:inline-block;border-bottom:1px solid #000;min-width:80px;padding:0 6px;font-weight:bold;">${certEscHtml(String(cert.verified_heads || req.head_count || ''))}</span></td>
              <td style="padding-top:5px;"><span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;">Php ${valNum.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</span></td>
            </tr>
          </tbody>
        </table>

        <div style="display:flex;justify-content:space-between;margin-bottom:22px;">
          <div style="width:52%;">
            <div style="margin-bottom:8px;">Means of transport: <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 4px;font-weight:bold;">${certEscHtml(cert.transport_means || req.transport_means || '')}</span></div>
            <div style="margin-bottom:8px;">Destination: <span style="display:inline-block;border-bottom:1px solid #000;min-width:220px;padding:0 4px;font-weight:bold;">${certEscHtml(cert.destination || '')}</span></div>
            <div style="margin-bottom:8px;">Plate No.: <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 4px;font-weight:bold;">${certEscHtml(cert.plate_number || req.plate_number || '')}</span></div>
            <div style="margin-top:15px;margin-bottom:8px;">Shipper: <span style="display:inline-block;border-bottom:1px solid #000;min-width:220px;padding:0 4px;font-weight:bold;">${certEscHtml(cert.shipper || req.shipper_name || req.farmer_name || '')}</span></div>
          </div>
          <div style="width:46%;">
            <div style="margin-bottom:8px;">Origin: <strong>${certEscHtml(cert.origin || (muni + ', Camarines Sur'))}</strong></div>
            <div style="margin-bottom:8px;">Purpose: ( <strong>${isCommercial ? '✓' : '&nbsp;'}</strong> ) Commercial &nbsp;&nbsp; ( <strong>${isPersonal ? '✓' : '&nbsp;'}</strong> ) Personal</div>
            <div style="margin-top:38px;margin-bottom:8px;">Address: <span style="display:inline-block;border-bottom:1px solid #000;min-width:210px;padding:0 4px;font-weight:bold;">${certEscHtml(cert.shipper_address || req.shipper_address || req.farmer_address || '')}</span></div>
          </div>
        </div>

        <p style="margin-top:25px;">
          Issued this <span style="display:inline-block;border-bottom:1px solid #000;min-width:45px;padding:0 4px;font-weight:bold;text-align:center;">${certEscHtml(issuedParts.day)}</span>
          day of <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;text-align:center;">${certEscHtml(issuedParts.monthYear)}</span>
          at the Municipal Agriculture Office, ${certEscHtml(muni)}, Camarines Sur.
        </p>

        <div style="margin-top:30px;margin-bottom:22px;">
          <div style="text-align:right;margin-bottom:25px;">
            <div style="display:inline-block;text-align:center;">
              <div style="height:26px;"></div>
              <span style="font-weight:bold;border-bottom:1px solid #000;padding:0 10px 2px 10px;min-width:220px;display:inline-block;">${certEscHtml(cert.vet_name || '')}</span><br>
              <span>${certEscHtml(cert.vet_position || 'Veterinarian I')}</span>
            </div>
          </div>
          <div style="text-align:left;">
            <div>NOTED:</div>
            <div style="display:inline-block;text-align:center;margin-top:20px;">
              <div style="height:22px;"></div>
              <span style="font-weight:bold;border-bottom:1px solid #000;padding:0 10px 2px 10px;min-width:220px;display:inline-block;">${certEscHtml(cert.municipal_agriculturist || '')}</span><br>
              <span>Municipal Agriculturist</span>
            </div>
          </div>
        </div>

        <div style="margin-bottom:20px;">
          <div style="margin-bottom:6px;">Paid under O. R. No.: <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;">${certEscHtml(cert.or_number || '')}</span></div>
          <div style="margin-bottom:6px;">Amount paid: Php <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;">${amtNum.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</span></div>
          <div style="margin-bottom:6px;">Date: <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;">${certEscHtml(payDate)}</span></div>
          <div style="margin-bottom:6px;">Place of Issue: <strong>${certEscHtml(cert.place_of_issue || ('Municipal Treasury Office, ' + muni + ', Camarines Sur'))}</strong></div>
        </div>

        <div style="margin-bottom:24px;">
          <div>PLEASE NOTE:</div>
          <ol style="margin:5px 0 0 20px;padding:0;">
            <li style="margin-bottom:4px;">This certification is valid until <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;text-align:center;">${certEscHtml(validUntil)}</span> and subject to cancellation should a disease outbreak occur in the place of origin.</li>
            <li style="margin-bottom:4px;">The above-mentioned animal(s) is/are still subject for further inspection for any purpose(s) deemed necessary.</li>
            <li style="margin-bottom:4px;">This is a requirement for the issuance of Veterinary Health Certificate.</li>
          </ol>
        </div>

        <div style="text-align:center;margin-top:24px;page-break-inside:avoid;">
          <div style="font-style:italic;font-weight:bold;font-size:13pt;color:#444;margin-bottom:5px;line-height:1.2;">Better Greater Happier<br>${certEscHtml(muni)}</div>
          <div style="font-size:9pt;color:#333;">
            Office of the Municipal Agriculturist, 2F Municipal Bldg., Poblacion, ${certEscHtml(muni)}, Camarines Sur<br>
            Tel No.: 331-22-46 Email Add.: maoragay01@gmail.com
          </div>
        </div>
      </div>
    `;
  }

  // EXACT OFFICIAL VETERINARY HEALTH CERTIFICATE (VHC) HTML TEMPLATE
  const certDate = farmerFmtLongDate(cert.issue_date);
  const expDate = farmerFmtLongDate(cert.expiry_date);
  const shipDate = farmerFmtLongDate(cert.shipment_date || req.proposed_shipment_date || cert.issue_date);
  const ownerName = cert.owner_name || req.farmer_name || cert.farmer_name || '';
  const ownerAddr = cert.owner_address || [req.farmer_address, req.farmer_barangay, req.farmer_municipality || muni].filter(Boolean).join(', ');
  let trRows = [];
  try {
    const rawTr = cert.treatment_rows || req.treatment_history;
    if (rawTr) {
      const p = typeof rawTr === 'string' ? JSON.parse(rawTr) : rawTr;
      if (Array.isArray(p)) trRows = p;
    }
  } catch (e) {}
  while (trRows.length < 2) trRows.push({ date: 'N/A', medicine: 'N/A', route: 'N/A', withdrawal: 'N/A' });

  return `
    <div id="farmerPrintableCertSheet" class="printable-cert-sheet" style="background:#fff;width:7in;max-width:100%;margin:0 auto;padding:0.55in 0.6in;box-sizing:content-box;font-family:'Cambria','Times New Roman',Times,serif;font-size:11pt;line-height:1.45;color:#000;box-shadow:0 4px 16px rgba(0,0,0,.08);">
      <div style="text-align:center;font-weight:bold;font-size:17pt;letter-spacing:0.5px;margin:0 0 24px 0;">VETERINARY HEALTH CERTIFICATE</div>

      <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:26px;">
        <div style="text-align:center;">
          <span style="display:block;border-bottom:1px solid #000;min-width:180px;min-height:1.4em;padding:0 6px;font-weight:bold;">${certEscHtml(certDate)}</span>
          <span style="font-size:10pt;">Date</span>
        </div>
        <div style="text-align:center;">
          <span style="display:block;border-bottom:1px solid #000;min-width:190px;min-height:1.4em;padding:0 6px;font-weight:bold;">${certEscHtml(cert.control_number || '')}</span>
          <span style="font-size:10pt;">VHC Control Number</span>
        </div>
      </div>

      <div style="font-weight:bold;margin-bottom:14px;">TO WHOM IT MAY CONCERN:</div>

      <p style="text-align:justify;margin:0 0 22px 0;">
        This is to certify that the animals described below were inspected on the date stated above by the
        undersigned and found to be free from signs of dangerous communicable disease/s. The animal/s are
        sourced from farm/compartment zone with no reported cases/outbreak of priority swine diseases during
        the last 21 days. To the best of my knowledge the animal/s have not been exposed to a disease and
        did not come from farm/s under quarantine.
      </p>

      <table style="width:100%;border-collapse:collapse;border-top:2px solid #000;border-bottom:2px solid #000;">
        <thead>
          <tr>
            <th style="border:1px solid #000;padding:4px 8px;text-align:center;font-weight:normal;text-transform:uppercase;font-size:10.5pt;">Species</th>
            <th style="border:1px solid #000;padding:4px 8px;text-align:center;font-weight:normal;text-transform:uppercase;font-size:10.5pt;">No. of Heads</th>
            <th style="border:1px solid #000;padding:4px 8px;text-align:center;font-weight:normal;text-transform:uppercase;font-size:10.5pt;">Sex</th>
            <th style="border:1px solid #000;padding:4px 8px;text-align:center;font-weight:normal;text-transform:uppercase;font-size:10.5pt;">Age</th>
            <th style="border:1px solid #000;padding:4px 8px;text-align:center;font-weight:normal;text-transform:uppercase;font-size:10.5pt;">Purpose</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="border:1px solid #000;padding:6px 8px;text-align:center;font-weight:bold;">${certEscHtml(cert.verified_species || req.animal_species || '')}</td>
            <td style="border:1px solid #000;padding:6px 8px;text-align:center;font-weight:bold;">${certEscHtml(String(cert.verified_heads || req.head_count || ''))}</td>
            <td style="border:1px solid #000;padding:6px 8px;text-align:center;font-weight:bold;">${certEscHtml(cert.verified_sex || req.sex || '')}</td>
            <td style="border:1px solid #000;padding:6px 8px;text-align:center;font-weight:bold;">${certEscHtml(cert.verified_age || req.animal_age || '')}</td>
            <td style="border:1px solid #000;padding:6px 8px;text-align:center;font-weight:bold;">${certEscHtml(cert.purpose || req.purpose || '')}</td>
          </tr>
          <tr>
            <td style="border:1px solid #000;height:26px;">&nbsp;</td>
            <td style="border:1px solid #000;"></td>
            <td style="border:1px solid #000;"></td>
            <td style="border:1px solid #000;"></td>
            <td style="border:1px solid #000;"></td>
          </tr>
        </tbody>
      </table>

      <div style="margin:20px 0 18px 0;">
        <div style="margin-bottom:6px;">Owner: <span style="display:inline-block;border-bottom:1px solid #000;min-width:340px;padding:0 6px;font-weight:bold;">${certEscHtml(ownerName)}</span></div>
        <div style="margin-bottom:6px;">Address: <span style="display:inline-block;border-bottom:1px solid #000;min-width:330px;padding:0 6px;font-weight:bold;">${certEscHtml(ownerAddr)}</span></div>
        <div style="margin-bottom:6px;">Origin: <span style="display:inline-block;border-bottom:1px solid #000;min-width:340px;padding:0 6px;font-weight:bold;">${certEscHtml(cert.origin || '')}</span></div>
        <div style="margin-bottom:6px;">Destination: <span style="display:inline-block;border-bottom:1px solid #000;min-width:315px;padding:0 6px;font-weight:bold;">${certEscHtml(cert.destination || '')}</span></div>
        <div style="margin-bottom:6px;">Date of Shipment: <span style="display:inline-block;border-bottom:1px solid #000;min-width:275px;padding:0 6px;font-weight:bold;">${certEscHtml(shipDate)}</span></div>
      </div>

      <div style="margin-bottom:4px;font-weight:bold;">Treatment/Medication History</div>
      <table style="width:100%;border-collapse:collapse;border-top:2px solid #000;border-bottom:2px solid #000;">
        <thead>
          <tr>
            <th style="border:1px solid #000;padding:4px 8px;width:18%;font-weight:normal;text-transform:uppercase;font-size:10pt;">Date</th>
            <th style="border:1px solid #000;padding:4px 8px;width:32%;font-weight:normal;text-transform:uppercase;font-size:10pt;">Medicine Administered</th>
            <th style="border:1px solid #000;padding:4px 8px;width:25%;font-weight:normal;text-transform:uppercase;font-size:10pt;">Route</th>
            <th style="border:1px solid #000;padding:4px 8px;width:25%;font-weight:normal;text-transform:uppercase;font-size:10pt;">Withdrawal Period</th>
          </tr>
        </thead>
        <tbody>
          ${trRows.map(r => `<tr>
            <td style="border:1px solid #000;padding:5px 8px;">${certEscHtml(r.date || 'N/A')}</td>
            <td style="border:1px solid #000;padding:5px 8px;">${certEscHtml(r.medicine || 'N/A')}</td>
            <td style="border:1px solid #000;padding:5px 8px;">${certEscHtml(r.route || 'N/A')}</td>
            <td style="border:1px solid #000;padding:5px 8px;">${certEscHtml(r.withdrawal || 'N/A')}</td>
          </tr>`).join('')}
        </tbody>
      </table>

      <p style="margin:22px 0 30px 0;">
        This certificate shall expire on <span style="display:inline-block;border-bottom:1px solid #000;min-width:190px;padding:0 6px;font-weight:bold;text-align:center;">${certEscHtml(expDate)}</span>, and automatically cancelled thereafter.
      </p>

      <div style="margin-left:0;">
        <div style="height:40px;"></div>
        <div style="font-weight:bold;text-decoration:underline;text-transform:uppercase;">${certEscHtml(cert.vet_name || 'Dana Mar F. San Andres, DVM')}</div>
        <div style="margin-top:2px;">${certEscHtml(cert.vet_position || (muni + ' LGU Veterinarian'))}</div>
        <div style="margin-top:2px;">PRC License No.: <span style="display:inline-block;border-bottom:1px solid #000;min-width:200px;padding:0 6px;">${certEscHtml(cert.prc_license_no || '')}</span></div>
        <div style="margin-top:2px;">Validity: <span style="display:inline-block;border-bottom:1px solid #000;min-width:200px;padding:0 6px;">${certEscHtml(cert.prc_validity || '')}</span></div>
        <div style="margin-top:2px;">Contact No.: <span style="display:inline-block;border-bottom:1px solid #000;min-width:200px;padding:0 6px;">${certEscHtml(cert.vet_contact_no || '')}</span></div>
      </div>

      <div style="margin-top:36px;font-size:10.5pt;text-align:justify;">
        Note: This is not a valid &ldquo;Shipping Permit&rdquo;, please secure a Shipping Permit from the National Veterinary
        Quarantine Service-Veterinary Quarantine Station 5, (nvqsd.bai.gov.ph). ANY UNAUTHORIZED
        ALTERATION INVALIDATES THIS CERTIFICATE.
      </div>
    </div>
  `;
}

function printOfficialCertificateDocument() {
  const printContent = document.getElementById('certPrintDocumentWrapper')?.innerHTML;
  if (!printContent) return;
  const ctrl = farmerCurrentCert ? (farmerCurrentCert.control_number || 'Official_Certificate') : 'Official_Certificate';
  const w = window.open('', '_blank');
  if (!w) { alert('Please allow pop-ups to print your certificate.'); return; }
  w.document.write(`<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>${certEscHtml(ctrl)}</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; background: #fff; color: #000; }
    .printable-cert-sheet { box-shadow: none !important; margin: 0 auto !important; }
    @media print {
      @page { size: letter portrait; margin: 0.4in; }
      body { padding: 0; }
      .printable-cert-sheet { padding: 0 !important; width: 100% !important; }
    }
  </style>
</head>
<body onload="setTimeout(function(){ window.print(); }, 200);">
  ${printContent}
</body>
</html>`);
  w.document.close();
}

async function downloadFarmerOfficialCertPdf() {
  const sheet = document.getElementById('farmerPrintableCertSheet') || document.querySelector('#certPrintDocumentWrapper .printable-cert-sheet');
  if (!sheet) { alert('Certificate preview is not ready yet.'); return; }
  const ctrl = farmerCurrentCert ? (farmerCurrentCert.control_number || 'CERTIFICATE') : 'CERTIFICATE';
  const filename = `${ctrl}_Official_Certificate.pdf`;
  const btn = document.getElementById('btnFarmerCertDownloadPdf');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating PDF...'; }
  try {
    if (typeof html2pdf === 'undefined') throw new Error('html2pdf library not loaded');
    if (document.fonts && document.fonts.ready) await document.fonts.ready;
    await html2pdf().set({
      margin: [0.3, 0.3, 0.3, 0.3],
      filename: filename,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true, logging: false },
      jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    }).from(sheet).save();
  } catch (e) {
    console.error(e);
    alert('Direct PDF download unavailable, opening print dialog instead.');
    printOfficialCertificateDocument();
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-file-earmark-pdf-fill"></i> Download PDF'; }
  }
}
</script>
<?php include __DIR__ . '/official_form_print.php'; ?>
<script src="<?= app_url('assets/js/translator.js') ?>"></script>
</body>
</html>