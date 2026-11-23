<?php
// Universal directory resolver: works both in root (flat) and in subfolder (/pages/)
$baseDir = file_exists(__DIR__ . '/helpers.php') ? __DIR__ : dirname(__DIR__);
chdir($baseDir);

require_once $baseDir . '/init.php';
require_once $baseDir . '/helpers.php';
$googleMapsApiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['PHP_SELF']));
    exit;
}

$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['Admin', 'Agriculture Official'], true)) {
    header('Location: dashboard.php');
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
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
              `vet_name` varchar(255) NOT NULL DEFAULT ', DVM',
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
            $ensureCol('assistance_certificates', 'inspection_date', "DATE DEFAULT NULL");
            $ensureCol('assistance_certificates', 'owner_name', "VARCHAR(255) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'owner_address', "TEXT DEFAULT NULL");
            $ensureCol('assistance_certificates', 'shipment_date', "DATE DEFAULT NULL");
            $ensureCol('assistance_certificates', 'prc_license_no', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'prc_validity', "VARCHAR(100) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'vet_contact_no', "VARCHAR(60) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'municipal_agriculturist', "VARCHAR(255) DEFAULT NULL");
            $ensureCol('assistance_certificates', 'treatment_rows', "TEXT DEFAULT NULL");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `indemnity_claim_certificates` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `insurance_id` int(11) DEFAULT NULL,
              `farmer_id` int(11) DEFAULT NULL,
              `control_number` varchar(80) NOT NULL,
              `farmer_name` varchar(255) NOT NULL,
              `farmer_barangay` varchar(150) DEFAULT NULL,
              `municipality` varchar(100) NOT NULL DEFAULT 'Ragay',
              `animal_kind_gender` varchar(150) NOT NULL,
              `date_of_loss` date NOT NULL,
              `cause_of_death` varchar(255) NOT NULL,
              `estimated_loss` decimal(12,2) DEFAULT 0.00,
              `req_brgy_cert` tinyint(1) NOT NULL DEFAULT 0,
              `witness1_name` varchar(255) DEFAULT NULL,
              `witness1_cedula` varchar(100) DEFAULT NULL,
              `witness1_affidavit` tinyint(1) NOT NULL DEFAULT 0,
              `witness1_valid_id` tinyint(1) NOT NULL DEFAULT 0,
              `witness1_same_brgy_unrelated` tinyint(1) NOT NULL DEFAULT 0,
              `witness2_name` varchar(255) DEFAULT NULL,
              `witness2_cedula` varchar(100) DEFAULT NULL,
              `witness2_affidavit` tinyint(1) NOT NULL DEFAULT 0,
              `witness2_valid_id` tinyint(1) NOT NULL DEFAULT 0,
              `witness2_same_brgy_unrelated` tinyint(1) NOT NULL DEFAULT 0,
              `req_owner_affidavit` tinyint(1) NOT NULL DEFAULT 0,
              `req_dead_animal_photos` tinyint(1) NOT NULL DEFAULT 0,
              `photo_receiver_note` varchar(255) DEFAULT NULL,
              `vet_name` varchar(255) DEFAULT NULL,
              `municipal_agriculturist` varchar(255) DEFAULT NULL,
              `issue_date` date NOT NULL,
              `remarks` text DEFAULT NULL,
              `status` enum('Issued','Revoked') NOT NULL DEFAULT 'Issued',
              `created_by` int(11) DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_indemnity_ctrl` (`control_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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
        } catch(Throwable $e) {
            error_log("Assistance tables check: " . $e->getMessage());
        }
    }
}
    ensure_assistance_tables($pdo);
}

// -- REPORT EXPORTS (PDF / Excel / CSV) -- uses the styled export engine -------
// Engine file = the 2nd <?php block of this document, saved as export_helper.php
if (isset($_GET['export'])) {
    foreach ([__DIR__ . '/export_helper.php', dirname(__DIR__) . '/export_helper.php', __DIR__ . '/api/export_helper.php'] as $__eng) {
        if (is_file($__eng)) { require_once $__eng; break; }
    }
    if (!function_exists('agxSend')) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Export failed: export_helper.php not found.');
    }
    $exp    = $_GET['export'];
    $fmt    = $_GET['format'] ?? 'xlsx';
    $by     = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? '')) ?: 'Agriculture Official';
    $incSql = "SELECT i.*, f.name AS farm_name, f.pet_municipality,
                      CONCAT_WS(' ', u.firstName, u.lastName) AS reporter
               FROM incidents i
               LEFT JOIN farms f ON i.farmId = f.id
               LEFT JOIN users u ON i.reporterId = u.id";

    if ($exp === 'archive_incidents') {
        $rows = $pdo->query($incSql . " WHERE i.status IN ('Resolved','Closed') ORDER BY i.resolvedAt DESC")->fetchAll(PDO::FETCH_ASSOC);
        $ttl  = 'Archived Incidents';
        $sub  = 'Resolved and closed farm incidents';
        agxSend($fmt, agx_summary_for('incidents', $rows, $ttl, $sub),
                [agx_dataset('incidents', $rows, $ttl, $sub, 'Archived Incidents')], $by, 'agritrace_archived_incidents');
    }

    if ($exp === 'archive_reports') {
        $rows = $pdo->query("SELECT * FROM public_reports WHERE status IN ('Resolved','Closed') ORDER BY createdAt DESC")->fetchAll(PDO::FETCH_ASSOC);
        $ttl  = 'Archived Public Reports';
        $sub  = 'Resolved and closed citizen reports';
        agxSend($fmt, agx_summary_for('public_reports', $rows, $ttl, $sub),
                [agx_dataset('public_reports', $rows, $ttl, $sub, 'Archived Reports')], $by, 'agritrace_archived_reports');
    }

    if ($exp === 'analytics') {
        $farms = $pdo->query("
            SELECT f.*, u.firstName, u.lastName, u.email,
                   (SELECT COUNT(*) FROM livestock WHERE farmId = f.id) AS livestock_records,
                   (SELECT COALESCE(SUM(qty),0) FROM livestock WHERE farmId = f.id) AS total_livestock,
                   (SELECT GROUP_CONCAT(DISTINCT type SEPARATOR ', ') FROM livestock WHERE farmId = f.id) AS livestock_types
            FROM farms f LEFT JOIN users u ON f.ownerId = u.id
            ORDER BY f.createdAt DESC")->fetchAll(PDO::FETCH_ASSOC);

        $stock = $pdo->query("
            SELECT l.*, f.name AS farm_name, f.pet_municipality, u.firstName, u.lastName
            FROM livestock l
            JOIN farms f ON l.farmId = f.id
            LEFT JOIN users u ON f.ownerId = u.id
            ORDER BY l.createdAt DESC")->fetchAll(PDO::FETCH_ASSOC);

        $incs = $pdo->query($incSql . " ORDER BY i.createdAt DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($incs as &$__i) { $__i['source'] = 'Farmer Incident'; $__i['source_table'] = 'incidents'; }
        unset($__i);
        $pubs = $pdo->query("SELECT * FROM public_reports ORDER BY createdAt DESC")->fetchAll(PDO::FETCH_ASSOC);
        $merged = $incs;
        foreach ($pubs as $p) {
            $merged[] = [
                'id' => $p['id'], 'source' => 'Public Report', 'source_table' => 'public_reports',
                'title' => ucfirst($p['reportType']) . ' report', 'type' => $p['reportType'],
                'description' => $p['description'], 'priority' => 'Public', 'status' => $p['status'],
                'contactPhone' => $p['contactPhone'], 'contactEmail' => $p['contactEmail'],
                'latitude' => $p['latitude'], 'longitude' => $p['longitude'], 'createdAt' => $p['createdAt'],
            ];
        }

        $heads   = (int)agx_sum_key($stock, 'qty');
        $nFarms  = count($farms);
        $nInc    = count($incs);
        $nAct    = agx_count_where($incs, 'status', ['Pending', 'In Progress']);
        $nApp    = agx_count_where($farms, 'status', ['Approved']);
        $nPend   = agx_count_where($farms, 'status', ['Pending']);
        $typeCnt = agx_sum_by($stock, function ($r) { return $r['type'] === 'Buffalo' ? 'Carabao' : $r['type']; }, agx_gn('qty'));
        $yearly  = $pdo->query("
            SELECT YEAR(f.createdAt) AS yr, COALESCE(SUM(l.qty),0) AS total
            FROM livestock l JOIN farms f ON l.farmId = f.id
            WHERE YEAR(f.createdAt) >= YEAR(CURDATE()) - 5
            GROUP BY yr ORDER BY yr ASC")->fetchAll(PDO::FETCH_ASSOC);
        $yearData = [];
        foreach ($yearly as $y) { $yearData[] = [(string)$y['yr'], (int)$y['total']]; }

        $summary = [
            'title' => 'Reports & Analytics',
            'sub'   => 'Livestock population, distribution, incident health and yearly trends across the district',
            'kpis'  => [
                ['TOTAL FARMS',      $nFarms, agx_c('emerald'), $nApp . ' approved &bull; ' . $nPend . ' pending'],
                ['TOTAL LIVESTOCK',  $heads,  agx_c('blue'),    count($typeCnt) . ' animal types'],
                ['AVG HEADS / FARM', $nFarms > 0 ? round($heads / $nFarms, 1) : 0.0, agx_c('violet'), 'per registered farm'],
                ['TOTAL INCIDENTS',  $nInc,   agx_c('amber'),   ($nInc - $nAct) . ' closed out'],
                ['ACTIVE INCIDENTS', $nAct,   agx_c('red'),     'need attention now'],
                ['PUBLIC REPORTS',   count($pubs), agx_c('green'), 'citizen submitted'],
            ],
            'charts' => [
                ['title' => 'Livestock Composition', 'type' => 'doughnut', 'data' => $typeCnt, 'colors' => 'palette', 'head' => ['Animal Type', 'Heads']],
                ['title' => 'Yearly Registration Trend (heads)', 'type' => 'column', 'data' => $yearData, 'colors' => '3B82F6', 'head' => ['Year', 'Heads']],
                ['title' => 'Incident Status', 'type' => 'doughnut', 'data' => agx_count_by($incs, agx_g('status'), ['Pending', 'In Progress', 'Resolved', 'Closed']), 'colors' => 'status', 'head' => ['Status', 'Incidents']],
                ['title' => 'Incident Priority', 'type' => 'column', 'data' => agx_count_by($incs, agx_g('priority'), ['Low', 'Medium', 'High', 'Critical']), 'colors' => 'status', 'head' => ['Priority', 'Incidents']],
            ],
        ];
        agxSend($fmt, $summary, [
            agx_dataset('farms', $farms),
            agx_dataset('livestock', $stock),
            agx_dataset('incidents', $merged),
        ], $by, 'agritrace_reports_analytics');
    }

    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Unknown export type.');
}

$stats = [
    'approved_farms'  => $pdo->query("SELECT COUNT(*) FROM farms WHERE status = 'Approved'")->fetchColumn(),
    'pending_farms'   => $pdo->query("SELECT COUNT(*) FROM farms WHERE status = 'Pending'")->fetchColumn(),
    'active_incidents'=> $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')")->fetchColumn(),
    'pending_reports' => $pdo->query("SELECT COUNT(*) FROM public_reports WHERE status = 'Pending'")->fetchColumn(),
    'total_livestock' => $pdo->query("SELECT COALESCE(SUM(qty), 0) FROM livestock")->fetchColumn(),
];

$livestock_by_type = $pdo->query("
    SELECT type, COALESCE(SUM(qty), 0) as total
    FROM livestock
    GROUP BY type
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT i.*, f.name as farm_name, u.firstName, u.lastName
    FROM incidents i
    LEFT JOIN farms f ON i.farmId = f.id
    LEFT JOIN users u ON i.reporterId = u.id
    ORDER BY i.createdAt DESC LIMIT 5
");
$recent_incidents = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT f.*, u.firstName, u.lastName,
           (SELECT COALESCE(SUM(qty), 0) FROM livestock WHERE farmId = f.id) as total_livestock
    FROM farms f
    LEFT JOIN users u ON f.ownerId = u.id
    ORDER BY (CASE WHEN f.status = 'Pending' THEN 0 ELSE 1 END), f.updatedAt DESC, f.createdAt DESC
");
$all_farms = $stmt->fetchAll();

// -- EXTENDED DATA FOR DA PORTAL MODULES --
$stats['pending_farmers']   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Farmer' AND status = 'Pending'")->fetchColumn();
$stats['total_farmers']     = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Farmer'")->fetchColumn();
$stats['pending_vet_req']   = $pdo->query("SELECT COUNT(*) FROM veterinary_requests WHERE status IN ('Pending','Assigned')")->fetchColumn();
$stats['pending_insurance'] = $pdo->query("SELECT COUNT(*) FROM insurance_assistance WHERE pcic_status = 'Pending Verification'")->fetchColumn();
$stats['pending_slaughter'] = $pdo->query("SELECT COUNT(*) FROM slaughter_declarations WHERE status = 'Pending Review'")->fetchColumn();
$stats['total_certs']       = $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();

// Query all farmers
$all_farmers = $pdo->query("
    SELECT u.*, 
           fp.gov_id, fp.gender, fp.dob, fp.address as profile_address, fp.emergency_contact, fp.profile_pix,
           (SELECT COUNT(*) FROM farms WHERE ownerId = u.id) as farm_count,
           (SELECT COALESCE(SUM(l.qty),0) FROM livestock l JOIN farms f ON l.farmId = f.id WHERE f.ownerId = u.id) as livestock_count
    FROM users u 
    LEFT JOIN farmer_profiles fp ON fp.user_id = u.id
    WHERE u.role = 'Farmer' 
    ORDER BY (CASE WHEN u.status = 'Pending' THEN 0 ELSE 1 END), u.createdAt DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Query all livestock with farm details
$all_livestock = $pdo->query("
    SELECT l.*, f.name as farm_name, f.pet_municipality, f.address as farm_address, f.latitude as farm_lat, f.longitude as farm_lng,
           u.firstName, u.lastName, u.mobile, u.email as owner_email,
           (SELECT checkup_date FROM livestock_health_records WHERE livestock_id = l.id ORDER BY checkup_date DESC LIMIT 1) as latest_checkup
    FROM livestock l
    JOIN farms f ON l.farmId = f.id
    LEFT JOIN users u ON f.ownerId = u.id
    ORDER BY l.createdAt DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Query all health records
$all_health_records = $pdo->query("
    SELECT hr.*, l.tagId, l.type as livestock_type, l.breed, f.name as farm_name, f.pet_municipality,
           u.firstName, u.lastName
    FROM livestock_health_records hr
    JOIN livestock l ON hr.livestock_id = l.id
    JOIN farms f ON l.farmId = f.id
    LEFT JOIN users u ON f.ownerId = u.id
    ORDER BY hr.checkup_date DESC, hr.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Query veterinary requests
$all_vet_requests = $pdo->query("
    SELECT vr.*, f.name as farm_name, f.pet_municipality, f.address as farm_address,
           u.firstName, u.lastName, u.mobile, u.email as farmer_email,
           off.firstName as officer_first, off.lastName as officer_last
    FROM veterinary_requests vr
    JOIN farms f ON vr.farm_id = f.id
    LEFT JOIN users u ON vr.farmer_id = u.id
    LEFT JOIN users off ON vr.assigned_officer_id = off.id
    ORDER BY (CASE WHEN vr.status = 'Pending' THEN 0 WHEN vr.status = 'Assigned' THEN 1 ELSE 2 END), vr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Query unified farmer assistance requests & certificates
$all_assistance_requests = [];
$all_assistance_certs = [];
try {
    $all_assistance_requests = $pdo->query("
        SELECT ar.*,
               (SELECT COUNT(*) FROM assistance_certificates WHERE request_id = ar.id AND cert_type = 'VHC') as vhc_count,
               (SELECT COUNT(*) FROM assistance_certificates WHERE request_id = ar.id AND cert_type = 'COI') as coi_count
        FROM farmer_assistance_requests ar
        ORDER BY (CASE WHEN ar.status IN ('Request Submitted','Pending Review') THEN 0 
                       WHEN ar.status IN ('Under Review','For Inspection') THEN 1 
                       WHEN ar.status = 'Certificate Processing' THEN 2 
                       ELSE 3 END), ar.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $all_assistance_certs = $pdo->query("
        SELECT ac.*, ar.reference_no as req_ref, ar.farmer_name, ar.assistance_type
        FROM assistance_certificates ac
        JOIN farmer_assistance_requests ar ON ac.request_id = ar.id
        ORDER BY ac.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $eAssQ) {
    error_log("Assistance requests query: " . $eAssQ->getMessage());
}

// Query disease surveillance incidents
$disease_surveillance = $pdo->query("
    SELECT i.*, f.name as farm_name, f.pet_municipality, f.latitude as farm_lat, f.longitude as farm_lng,
           u.firstName, u.lastName
    FROM incidents i
    LEFT JOIN farms f ON i.farmId = f.id
    LEFT JOIN users u ON i.reporterId = u.id
    WHERE i.type IN ('Disease', 'Sick') OR i.priority IN ('High', 'Critical')
    ORDER BY (CASE WHEN i.status IN ('Pending','In Progress') THEN 0 ELSE 1 END), i.createdAt DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Query slaughter declarations
$all_slaughter = $pdo->query("
    SELECT sd.*, f.name as farm_name, f.pet_municipality, f.address as farm_address,
           u.firstName, u.lastName, u.mobile, u.email as farmer_email
    FROM slaughter_declarations sd
    JOIN farms f ON sd.farm_id = f.id
    LEFT JOIN users u ON sd.farmer_id = u.id
    ORDER BY (CASE WHEN sd.status = 'Pending Review' THEN 0 ELSE 1 END), sd.slaughter_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Query insurance referrals
$all_insurance = $pdo->query("
    SELECT ia.*, f.name as farm_name, f.pet_municipality, f.pet_barangay, u.firstName, u.lastName, u.mobile
    FROM insurance_assistance ia
    JOIN farms f ON ia.farm_id = f.id
    LEFT JOIN users u ON ia.farmer_id = u.id
    ORDER BY (CASE WHEN ia.pcic_status = 'Pending Verification' THEN 0 ELSE 1 END), ia.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$all_indemnity_certs = [];
try {
    $all_indemnity_certs = $pdo->query("SELECT * FROM indemnity_claim_certificates ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $eInd) {}

// Query certificates
$all_certificates = $pdo->query("
    SELECT c.*, f.name as farm_name, f.pet_municipality, u.firstName, u.lastName, u.mobile
    FROM certificates c
    LEFT JOIN farms f ON c.farm_id = f.id
    LEFT JOIN users u ON (c.farmer_id = u.id OR f.ownerId = u.id)
    ORDER BY (CASE WHEN c.status = 'Pending' THEN 0 ELSE 1 END), c.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);


if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" && isset($_POST['action'])) {
    @ini_set('display_errors', '0');
    if (ob_get_level() === 0) { ob_start(); }
    header('Content-Type: application/json');

    // Resolve $officerMuni before any POST action uses it
    $officerMuni = '';
    try {
        $stMuni = $pdo->prepare("SELECT municipality FROM officer_profiles WHERE user_id = ? LIMIT 1");
        $stMuni->execute([$user_id]);
        $rawMuniPost = trim((string)($stMuni->fetchColumn() ?: ''));
        foreach (['Ragay', 'Sipocot', 'Lupi', 'Del Gallego', 'Cabusao'] as $lguItem) {
            if (stripos($rawMuniPost, $lguItem) !== false) { $officerMuni = $lguItem; break; }
        }
    } catch (Throwable $eM) {}
    if (!$officerMuni && !empty($_SESSION['officer_municipality'])) {
        $officerMuni = (string)$_SESSION['officer_municipality'];
    }
    if (!$officerMuni) { $officerMuni = 'Ragay'; }

    try {
        $pdo->beginTransaction();
        if ($_POST['action'] === 'approve_farm') {
            $farm_id = (int)$_POST['farm_id'];
            $stmt = $pdo->prepare("UPDATE farms SET status = 'Approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'Pending'");
            $result = $stmt->execute([$user_id, $farm_id]);
            if ($result && $stmt->rowCount() > 0) {
                // Notify the farm owner
                $fStmt = $pdo->prepare("SELECT ownerId, name, application_type FROM farms WHERE id = ?");
                $fStmt->execute([$farm_id]);
                $fRow = $fStmt->fetch(PDO::FETCH_ASSOC);
                if ($fRow && !empty($fRow['ownerId'])) {
                    $isRenewal = strtolower($fRow['application_type'] ?? '') === 'renewal';
                    $notifTitle = $isRenewal ? "[APPROVED] Farm Registration Renewal Approved" : "[APPROVED] Farm Registration Approved";
                    $notifMsg = $isRenewal
                        ? "Your farm renewal application for \"" . $fRow['name'] . "\" has been officially APPROVED by the Department of Agriculture."
                        : "Your farm application for \"" . $fRow['name'] . "\" has been officially APPROVED by the Department of Agriculture.";
                    try {
                        $pdo->prepare("INSERT INTO notifications(user_id, type, title, message, farm_id, priority, is_read, created_at) VALUES (?, 'FARM_APPROVED', ?, ?, ?, 'Medium', 0, NOW())")
                            ->execute([
                                $fRow['ownerId'],
                                $notifTitle,
                                $notifMsg,
                                $farm_id
                            ]);
                    } catch(Exception $eN) {}
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Farm approved successfully!']);
            }
            else throw new Exception('Farm not found or already processed');
        } elseif ($_POST['action'] === 'reject_farm') {
            $farm_id = (int)$_POST['farm_id'];
            $stmt = $pdo->prepare("UPDATE farms SET status = 'Rejected', rejected_by = ?, rejected_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'Pending'");
            $result = $stmt->execute([$user_id, $farm_id]);
            if ($result && $stmt->rowCount() > 0) {
                $fStmt = $pdo->prepare("SELECT ownerId, name, application_type FROM farms WHERE id = ?");
                $fStmt->execute([$farm_id]);
                $fRow = $fStmt->fetch(PDO::FETCH_ASSOC);
                if ($fRow && !empty($fRow['ownerId'])) {
                    $isRenewal = strtolower($fRow['application_type'] ?? '') === 'renewal';
                    $notifTitle = $isRenewal ? "[ATTENTION] Farm Registration Renewal Update" : "[ATTENTION] Farm Registration Update";
                    $notifMsg = $isRenewal
                        ? "Your farm renewal for \"" . $fRow['name'] . "\" requires attention or was not approved. Please consult your municipal DA officer."
                        : "Your farm registration for \"" . $fRow['name'] . "\" requires attention or was not approved. Please consult your municipal DA officer.";
                    try {
                        $pdo->prepare("INSERT INTO notifications(user_id, type, title, message, farm_id, priority, is_read, created_at) VALUES (?, 'FARM_REJECTED', ?, ?, ?, 'High', 0, NOW())")
                            ->execute([
                                $fRow['ownerId'],
                                $notifTitle,
                                $notifMsg,
                                $farm_id
                            ]);
                    } catch(Exception $eN) {}
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Farm rejected successfully!']);
            }
            else throw new Exception('Farm not found or already processed');
        } elseif ($_POST['action'] === 'update_incident_priority') {
            $incident_id = (int)$_POST['incident_id'];
            $new_priority = $_POST['priority'];
            if (!in_array($new_priority, ['Low','Medium','High','Critical'])) throw new Exception('Invalid priority');
            $stmt = $pdo->prepare("UPDATE incidents SET priority = ?, updatedAt = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([$new_priority, $incident_id]);
            if ($result && $stmt->rowCount() > 0) { $pdo->commit(); echo json_encode(['success' => true, 'message' => 'Priority updated!']); }
            else throw new Exception('Incident not found');
        } elseif ($_POST['action'] === 'update_report_priority') {
            $report_id = (int)$_POST['report_id'];
            $new_status = $_POST['status'];
            if (!in_array($new_status, ['Pending','Reviewed','Resolved','Closed'])) throw new Exception('Invalid status');
            $stmt = $pdo->prepare("UPDATE public_reports SET status = ? WHERE id = ?");
            $result = $stmt->execute([$new_status, $report_id]);
            if ($result && $stmt->rowCount() > 0) { $pdo->commit(); echo json_encode(['success' => true, 'message' => 'Report status updated!']); }
            else throw new Exception('Report not found');
        } elseif ($_POST['action'] === 'resolve_incident') {
            $incident_id = (int)$_POST['incident_id'];
            $stmt = $pdo->prepare("UPDATE incidents SET status = 'Resolved', resolvedAt = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([$incident_id]);
            if ($result && $stmt->rowCount() > 0) {
                $iStmt = $pdo->prepare("SELECT reporterId, title FROM incidents WHERE id = ?");
                $iStmt->execute([$incident_id]);
                $iRow = $iStmt->fetch(PDO::FETCH_ASSOC);
                if ($iRow && !empty($iRow['reporterId'])) {
                    try {
                        $pdo->prepare("INSERT INTO notifications(user_id, type, title, message, incident_id, priority, is_read, created_at) VALUES (?, 'INFO', ?, ?, ?, 'Medium', 0, NOW())")
                            ->execute([
                                $iRow['reporterId'],
                                "[RESOLVED] Incident Resolved: " . substr($iRow['title'], 0, 30),
                                "Your reported incident \"" . $iRow['title'] . "\" has been resolved and closed by the DA team.",
                                $incident_id
                            ]);
                    } catch(Exception $eN) {}
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Incident resolved!']);
            }
            else throw new Exception('Incident not found');
        } elseif ($_POST['action'] === 'mark_da_notif_read') {
            $nid = (int)($_POST['notif_id'] ?? 0);
            if ($nid > 0) {
                $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$nid, $user_id]);
            }
            $pdo->commit();
            $rem = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$user_id AND is_read=0")->fetchColumn();
            echo json_encode(['success' => true, 'unread_count' => $rem]);
            exit;
        } elseif ($_POST['action'] === 'mark_all_da_notifs_read') {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'unread_count' => 0]);
            exit;
        } elseif ($_POST['action'] === 'update_profile') {
            $uid = (int)$_POST['user_id'];
            $exStmt = $pdo->prepare("SELECT profile_pix FROM officer_profiles WHERE user_id = ?");
            $exStmt->execute([$uid]);
            $profile_pic_path = $exStmt->fetchColumn() ?: null;
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/profiles/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                    $fn = $uid . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $fn)) {
                        $profile_pic_path = $upload_dir . $fn;
                    }
                }
            }
            $stmt = $pdo->prepare("UPDATE users SET firstName=?, lastName=?, mobile=?, updatedAt=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([$_POST['firstName'], $_POST['lastName'], $_POST['mobile'] ?? null, $uid]);
            $_SESSION['firstName'] = $_POST['firstName'];
            $_SESSION['lastName']  = $_POST['lastName'];
            $stmt = $pdo->prepare("INSERT INTO officer_profiles (user_id,gov_id,department,position,office,assigned_region,municipality,province,profile_pix) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE gov_id=VALUES(gov_id),department=VALUES(department),position=VALUES(position),office=VALUES(office),assigned_region=VALUES(assigned_region),municipality=VALUES(municipality),province=VALUES(province),profile_pix=VALUES(profile_pix),updated_at=CURRENT_TIMESTAMP");
            $stmt->execute([$uid,$_POST['gov_id']??null,$_POST['department']??null,$_POST['position']??null,$_POST['office']??null,$_POST['assigned_region']??null,$_POST['municipality']??null,$_POST['province']??null,$profile_pic_path]);
            if (!empty($_POST['new_password'])) {
                $h = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$h, $uid]);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
            exit;
        } elseif ($_POST['action'] === 'change_officer_password') {
            $uid = $user_id;
            $cur = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $conf = $_POST['confirm_password'] ?? '';
            if (strlen($new) < 6) throw new Exception('New password must be at least 6 characters.');
            if ($new !== $conf) throw new Exception('Passwords do not match.');
            $chk = $pdo->prepare("SELECT password FROM users WHERE id=?");
            $chk->execute([$uid]);
            $currHash = $chk->fetchColumn();
            if ($currHash && !password_verify($cur, $currHash)) {
                throw new Exception('Current password is incorrect.');
            }
            $h = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$h, $uid]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
            exit;
        } elseif ($_POST['action'] === 'approve_farmer') {
            $uId = (int)$_POST['user_id'];
            $pdo->prepare("UPDATE users SET status = 'Active', updatedAt = CURRENT_TIMESTAMP WHERE id = ?")->execute([$uId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Farmer account approved and activated successfully!']);
            exit;
        } elseif ($_POST['action'] === 'deactivate_farmer') {
            $uId = (int)$_POST['user_id'];
            $pdo->prepare("UPDATE users SET status = 'Inactive', updatedAt = CURRENT_TIMESTAMP WHERE id = ?")->execute([$uId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Farmer account deactivated.']);
            exit;
        } elseif ($_POST['action'] === 'delete_farmer') {
            $uId = (int)$_POST['user_id'];
            $chk = $pdo->prepare("SELECT id, firstName, lastName, role FROM users WHERE id = ?");
            $chk->execute([$uId]);
            $targetUser = $chk->fetch();
            if (!$targetUser) {
                throw new Exception("Farmer account not found.");
            }
            if ($targetUser['role'] !== 'Farmer') {
                throw new Exception("Only farmer accounts can be deleted from this panel.");
            }

            // Find all farms owned by this farmer
            $farmStmt = $pdo->prepare("SELECT id FROM farms WHERE ownerId = ?");
            $farmStmt->execute([$uId]);
            $farmIds = $farmStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($farmIds)) {
                $inClause = implode(',', array_map('intval', $farmIds));
                try { $pdo->exec("DELETE FROM livestock_health_records WHERE livestock_id IN (SELECT id FROM livestock WHERE farmId IN ($inClause))"); } catch (Throwable $e) {}
                try { $pdo->exec("DELETE FROM certificates WHERE farm_id IN ($inClause)"); } catch (Throwable $e) {}
                try { $pdo->exec("DELETE FROM livestock WHERE farmId IN ($inClause)"); } catch (Throwable $e) {}
                try { $pdo->exec("DELETE FROM incidents WHERE farmId IN ($inClause)"); } catch (Throwable $e) {}
                try { $pdo->exec("DELETE FROM veterinary_requests WHERE farm_id IN ($inClause)"); } catch (Throwable $e) {}
                try { $pdo->exec("DELETE FROM insurance_assistance WHERE farm_id IN ($inClause)"); } catch (Throwable $e) {}
                try { $pdo->exec("DELETE FROM slaughter_declarations WHERE farm_id IN ($inClause)"); } catch (Throwable $e) {}
                try { $pdo->exec("DELETE FROM livestock_transfers WHERE origin_farm_id IN ($inClause) OR destination_farm_id IN ($inClause)"); } catch (Throwable $e) {}
                try { $pdo->exec("DELETE FROM farms WHERE id IN ($inClause)"); } catch (Throwable $e) {}
            }

            try { $pdo->prepare("DELETE FROM farm_registrations WHERE farmer_id = ?")->execute([$uId]); } catch (Throwable $e) {}
            try { $pdo->prepare("DELETE FROM rsbsa_submissions WHERE farmer_id = ?")->execute([$uId]); } catch (Throwable $e) {}
            try { $pdo->prepare("DELETE FROM pcic_submissions WHERE farmer_id = ?")->execute([$uId]); } catch (Throwable $e) {}
            try { $pdo->prepare("DELETE FROM farmer_profiles WHERE user_id = ?")->execute([$uId]); } catch (Throwable $e) {}
            try { $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$uId]); } catch (Throwable $e) {}
            try { $pdo->prepare("DELETE FROM chat_messages WHERE sender_id = ? OR receiver_id = ?")->execute([$uId, $uId]); } catch (Throwable $e) {}
            try { $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$uId, $uId]); } catch (Throwable $e) {}
            try { $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$uId]); } catch (Throwable $e) {}
            try { $pdo->prepare("DELETE FROM incidents WHERE reporterId = ?")->execute([$uId]); } catch (Throwable $e) {}

            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'Farmer'")->execute([$uId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Farmer account '{$targetUser['firstName']} {$targetUser['lastName']}' has been permanently deleted."]);
            exit;
        } elseif ($_POST['action'] === 'add_livestock') {
            $farmId = (int)$_POST['farm_id'];
            $chkFarm = $pdo->prepare("SELECT id FROM farms WHERE id = ?");
            $chkFarm->execute([$farmId]);
            if (!$chkFarm->fetch()) throw new Exception("Farm not found.");
            
            $tagId = trim($_POST['tagId'] ?? '');
            if (!$tagId) throw new Exception("Tag ID / Physical Tag Number is required.");
            $type = trim($_POST['type'] ?? '');
            if (!$type) throw new Exception("Animal species is required.");
            $farmingType = in_array($_POST['farming_type'] ?? '', ['Backyard', 'Commercial'], true) ? $_POST['farming_type'] : 'Backyard';
            $breed = trim($_POST['breed'] ?? '') ?: null;
            $qty = max(1, (int)($_POST['qty'] ?? 1));
            $age = !empty($_POST['age']) ? (float)$_POST['age'] : null;
            $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
            $health = $_POST['healthStatus'] ?? 'Healthy';
            $vax = $_POST['vaccinationStatus'] ?? 'None';
            $notes = trim($_POST['notes'] ?? '') ?: null;

            try {
                $pdo->prepare("INSERT INTO livestock(farmId,type,farming_type,tagId,breed,age,weight,qty,healthStatus,vaccinationStatus,notes)
                               VALUES(?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$farmId,$type,$farmingType,$tagId,$breed,$age,$weight,$qty,$health,$vax,$notes]);
            } catch(Throwable $dbErr) {
                $pdo->prepare("INSERT INTO livestock(farmId,type,tagId,breed,age,weight,qty,healthStatus,vaccinationStatus,notes)
                               VALUES(?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$farmId,$type,$tagId,$breed,$age,$weight,$qty,$health,$vax,$notes]);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Livestock record '$tagId' added successfully!"]);
            exit;
        } elseif ($_POST['action'] === 'assign_vet_request') {
            $reqId = (int)$_POST['request_id'];
            $newSt = $_POST['status'] ?? 'Assigned';
            $remarks = trim($_POST['remarks'] ?? '') ?: null;
            $pdo->prepare("UPDATE veterinary_requests SET assigned_officer_id = ?, status = ?, officer_remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$user_id, $newSt, $remarks, $reqId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Assistance request updated to '$newSt'."]);
            exit;
        } elseif ($_POST['action'] === 'update_insurance_status') {
            $insId = (int)$_POST['insurance_id'];
            $newSt = $_POST['status'] ?? 'Endorsed to PCIC';
            $code = trim($_POST['tracking_code'] ?? '') ?: null;
            $pdo->prepare("UPDATE insurance_assistance SET pcic_status = ?, claim_tracking_code = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$newSt, $code, $insId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "PCIC status updated to '$newSt'."]);
            exit;
        } elseif ($_POST['action'] === 'update_slaughter_status') {
            $slgId = (int)$_POST['declaration_id'];
            $newSt = $_POST['status'] ?? 'Verified';
            $notes = trim($_POST['notes'] ?? '') ?: null;
            $inspector = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? '')) ?: 'DA Meat Inspector';
            $pdo->prepare("UPDATE slaughter_declarations SET status = ?, inspected_by = ?, inspector_notes = ? WHERE id = ?")
                ->execute([$newSt, $inspector, $notes, $slgId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Slaughter declaration status set to '$newSt'."]);
            exit;
        } elseif ($_POST['action'] === 'delete_slaughter_declaration') {
            if (!in_array($user_role, ['Admin', 'Agriculture Official'], true)) {
                throw new Exception('Unauthorized access.');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid declaration ID.');
            $stmt = $pdo->prepare("DELETE FROM slaughter_declarations WHERE id = ?");
            $stmt->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Slaughter declaration deleted successfully!']);
            exit;
        } elseif ($_POST['action'] === 'delete_health_record') {
            if (!in_array($user_role, ['Admin', 'Agriculture Official'], true)) {
                throw new Exception('Unauthorized access.');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid health record ID.');
            $stmt = $pdo->prepare("DELETE FROM livestock_health_records WHERE id = ?");
            $stmt->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Animal health record deleted successfully!']);
            exit;
        } elseif ($_POST['action'] === 'delete_vet_request') {
            if (!in_array($user_role, ['Admin', 'Agriculture Official'], true)) {
                throw new Exception('Unauthorized access.');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid veterinary request ID.');
            $stmt = $pdo->prepare("DELETE FROM veterinary_requests WHERE id = ?");
            $stmt->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Veterinary assistance request deleted successfully!']);
            exit;
        } elseif ($_POST['action'] === 'update_assistance_status') {
            if (!in_array($user_role, ['Admin', 'Agriculture Official'], true)) {
                throw new Exception('Unauthorized access.');
            }
            $reqId = (int)($_POST['request_id'] ?? 0);
            $newSt = trim($_POST['status'] ?? 'Under Review');
            $validStatuses = ['Request Submitted','Pending Review','Under Review','For Inspection','Approved','Rejected','Certificate Processing','Certificate Issued','Completed'];
            if (!in_array($newSt, $validStatuses, true)) throw new Exception('Invalid status.');
            $remarks = trim($_POST['official_remarks'] ?? '') ?: null;

            $stGet = $pdo->prepare("SELECT farmer_id, reference_no, assistance_type FROM farmer_assistance_requests WHERE id = ?");
            $stGet->execute([$reqId]);
            $reqRow = $stGet->fetch(PDO::FETCH_ASSOC);
            if (!$reqRow) throw new Exception('Assistance request not found.');

            $pdo->prepare("UPDATE farmer_assistance_requests SET status = ?, official_remarks = ?, assigned_officer_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$newSt, $remarks, $user_id, $reqId]);

            // Notify farmer
            try {
                $pdo->prepare("INSERT INTO notifications(user_id, type, title, message, priority, is_read, created_at) VALUES(?, 'INFO', ?, ?, 'Medium', 0, NOW())")
                    ->execute([
                        $reqRow['farmer_id'],
                        "[STATUS UPDATE] {$reqRow['assistance_type']} ({$reqRow['reference_no']})",
                        "Your {$reqRow['assistance_type']} ({$reqRow['reference_no']}) status has been updated to: {$newSt}." . ($remarks ? " Remarks: {$remarks}" : "")
                    ]);
            } catch (Throwable $eN) {}

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Request {$reqRow['reference_no']} status updated to '{$newSt}'!"]);
            exit;
        } elseif ($_POST['action'] === 'encode_vhc_certificate') {
            if (!in_array($user_role, ['Admin', 'Agriculture Official'], true)) {
                throw new Exception('Unauthorized access.');
            }
            $reqId = (int)($_POST['request_id'] ?? 0);
            $stGet = $pdo->prepare("SELECT * FROM farmer_assistance_requests WHERE id = ?");
            $stGet->execute([$reqId]);
            $reqRow = $stGet->fetch(PDO::FETCH_ASSOC);
            if (!$reqRow) throw new Exception('Assistance request not found.');

            $ctrlNo = trim($_POST['control_number'] ?? '');
            if (!$ctrlNo) {
                $ctrlNo = 'VHC-' . strtoupper($officerMuni ?: 'RAG') . '-' . date('Y') . '-' . str_pad($reqId, 4, '0', STR_PAD_LEFT);
            }
            $issueDate = !empty($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d');
            $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : date('Y-m-d', strtotime('+3 days'));
            $vetName = trim($_POST['vet_name'] ?? '') ?: 'Dana Mar F. San Andres, DVM';
            $vetPos = trim($_POST['vet_position'] ?? (($officerMuni ?: 'Ragay') . ' LGU Veterinarian'));
            $placeOfIssue = trim($_POST['place_of_issue'] ?? '') ?: (($officerMuni ?: 'Ragay') . ', Camarines Sur');
            $ownerName = trim($_POST['owner_name'] ?? '') ?: ($reqRow['farmer_name'] ?? '');
            $ownerAddr = trim($_POST['owner_address'] ?? '') ?: ($reqRow['farmer_address'] ?? '');
            $shipmentDate = !empty($_POST['shipment_date']) ? $_POST['shipment_date'] : ($reqRow['proposed_shipment_date'] ?? null);
            $prcLicense = trim($_POST['prc_license_no'] ?? '');
            $prcValidity = trim($_POST['prc_validity'] ?? '');
            $vetContact = trim($_POST['vet_contact_no'] ?? '');
            $verSpecies = trim($_POST['verified_species'] ?? $reqRow['animal_species']);
            $verHeads = max(1, (int)($_POST['verified_heads'] ?? $reqRow['head_count']));
            $verSex = trim($_POST['verified_sex'] ?? $reqRow['sex']);
            $verAge = trim($_POST['verified_age'] ?? $reqRow['animal_age']);
            $purpose = trim($_POST['purpose'] ?? $reqRow['purpose']);
            $origin = trim($_POST['origin'] ?? '') ?: ($reqRow['origin_barangay'] . ', ' . $reqRow['origin_municipality']);
            $dest = trim($_POST['destination'] ?? '') ?: ($reqRow['dest_address'] ?: ($reqRow['dest_municipality'] ?: 'Camarines Sur'));
            $treatVer = trim($_POST['treatment_verification'] ?? '') ?: 'No clinical disease signs or active medication withdrawal restrictions observed.';
            $treatArr = [];
            for ($ti = 1; $ti <= 2; $ti++) {
                $td = trim($_POST['tr_date_' . $ti] ?? '');
                $tm = trim($_POST['tr_med_' . $ti] ?? '');
                $tr = trim($_POST['tr_route_' . $ti] ?? '');
                $tw = trim($_POST['tr_with_' . $ti] ?? '');
                if ($td !== '' || $tm !== '' || $tr !== '' || $tw !== '') {
                    $treatArr[] = ['date' => $td ?: 'N/A', 'medicine' => $tm ?: 'N/A', 'route' => $tr ?: 'N/A', 'withdrawal' => $tw ?: 'N/A'];
                }
            }
            $treatRowsJson = !empty($treatArr) ? json_encode($treatArr) : trim($_POST['treatment_rows'] ?? '');

            $stmtC = $pdo->prepare("INSERT INTO assistance_certificates (
                request_id, request_ref, farmer_id, cert_type, control_number,
                issue_date, expiry_date, municipality, place_of_issue, vet_name, vet_position,
                owner_name, owner_address, shipment_date, prc_license_no, prc_validity, vet_contact_no,
                verified_species, verified_heads, verified_sex, verified_age, purpose,
                origin, destination, treatment_verification, treatment_rows, status, created_by, created_at
            ) VALUES (?, ?, ?, 'VHC', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Issued', ?, NOW())
            ON DUPLICATE KEY UPDATE
                issue_date = VALUES(issue_date), expiry_date = VALUES(expiry_date),
                municipality = VALUES(municipality), place_of_issue = VALUES(place_of_issue),
                vet_name = VALUES(vet_name), vet_position = VALUES(vet_position),
                owner_name = VALUES(owner_name), owner_address = VALUES(owner_address),
                shipment_date = VALUES(shipment_date), prc_license_no = VALUES(prc_license_no),
                prc_validity = VALUES(prc_validity), vet_contact_no = VALUES(vet_contact_no),
                verified_species = VALUES(verified_species), verified_heads = VALUES(verified_heads),
                verified_sex = VALUES(verified_sex), verified_age = VALUES(verified_age),
                purpose = VALUES(purpose), origin = VALUES(origin), destination = VALUES(destination),
                treatment_verification = VALUES(treatment_verification), treatment_rows = VALUES(treatment_rows),
                updated_at = NOW()");

            $stmtC->execute([
                $reqId, $reqRow['reference_no'], $reqRow['farmer_id'], $ctrlNo,
                $issueDate, $expiryDate, ($officerMuni ?: 'Ragay'), $placeOfIssue, $vetName, $vetPos,
                $ownerName, $ownerAddr, $shipmentDate, $prcLicense, $prcValidity, $vetContact,
                $verSpecies, $verHeads, $verSex, $verAge, $purpose,
                $origin, $dest, $treatVer, $treatRowsJson, $user_id
            ]);

            $stCertOut = $pdo->prepare("SELECT ac.*, ar.reference_no as req_ref, ar.farmer_name, ar.assistance_type FROM assistance_certificates ac JOIN farmer_assistance_requests ar ON ac.request_id = ar.id WHERE ac.control_number = ? LIMIT 1");
            $stCertOut->execute([$ctrlNo]);
            $certSaved = $stCertOut->fetch(PDO::FETCH_ASSOC);

            $pdo->prepare("UPDATE farmer_assistance_requests SET status = 'Certificate Issued', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$reqId]);

            try {
                $pdo->prepare("INSERT INTO notifications(user_id, type, title, message, priority, is_read, created_at) VALUES(?, 'INFO', ?, ?, 'High', 0, NOW())")
                    ->execute([
                        $reqRow['farmer_id'],
                        "[CERTIFICATE ISSUED] Veterinary Health Certificate ({$ctrlNo})",
                        "Your Veterinary Health Certificate ({$ctrlNo}) has been officially prepared and issued by {$vetName} ({$officerMuni}). You can now view and download the official certificate in your portal."
                    ]);
            } catch (Throwable $eN) {}

            $pdo->commit();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => true, 'control_number' => $ctrlNo, 'cert' => $certSaved, 'message' => "Veterinary Health Certificate {$ctrlNo} officially encoded and issued!"]);
            exit;
        } elseif ($_POST['action'] === 'encode_coi_certificate') {
            if (!in_array($user_role, ['Admin', 'Agriculture Official'], true)) {
                throw new Exception('Unauthorized access.');
            }
            $reqId = (int)($_POST['request_id'] ?? 0);
            $stGet = $pdo->prepare("SELECT * FROM farmer_assistance_requests WHERE id = ?");
            $stGet->execute([$reqId]);
            $reqRow = $stGet->fetch(PDO::FETCH_ASSOC);
            if (!$reqRow) throw new Exception('Assistance request not found.');

            $ctrlNo = trim($_POST['control_number'] ?? '');
            if (!$ctrlNo) {
                $ctrlNo = 'COI-' . strtoupper($officerMuni ?: 'RAG') . '-' . date('Y') . '-' . str_pad($reqId, 4, '0', STR_PAD_LEFT);
            }
            $inspectionDate = !empty($_POST['inspection_date']) ? $_POST['inspection_date'] : date('Y-m-d');
            $issueDate = !empty($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d');
            $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : date('Y-m-d', strtotime('+3 days'));
            $vetName = trim($_POST['vet_name'] ?? '') ?: 'Dana Mar F. San Andres, DVM';
            $vetPos = trim($_POST['vet_position'] ?? 'Veterinarian I');
            $muniAgri = trim($_POST['municipal_agriculturist'] ?? '');
            $placeOfIssue = trim($_POST['place_of_issue'] ?? '') ?: ('Municipal Treasury Office, ' . ($officerMuni ?: 'Ragay') . ', Camarines Sur');
            $verSpecies = trim($_POST['verified_species'] ?? $reqRow['animal_species']);
            $verHeads = max(1, (int)($_POST['verified_heads'] ?? $reqRow['head_count']));
            $val = (float)($_POST['value'] ?? $reqRow['estimated_value']);
            $purpose = trim($_POST['purpose'] ?? ($reqRow['purpose'] ?: 'Commercial'));
            $origin = trim($_POST['origin'] ?? '') ?: (($officerMuni ?: 'Ragay') . ', Camarines Sur');
            $dest = trim($_POST['destination'] ?? '') ?: ($reqRow['dest_address'] ?: ($reqRow['dest_municipality'] ?: 'Camarines Sur'));
            $transMeans = trim($_POST['transport_means'] ?? '') ?: ($reqRow['transport_means'] ?: 'Forward Truck');
            $plate = trim($_POST['plate_number'] ?? '') ?: ($reqRow['plate_number'] ?: 'N/A');
            $shipper = trim($_POST['shipper'] ?? '') ?: ($reqRow['shipper_name'] ?: $reqRow['farmer_name']);
            $shipperAddr = trim($_POST['shipper_address'] ?? '') ?: ($reqRow['shipper_address'] ?: $reqRow['farmer_address']);
            $orNumber = trim($_POST['or_number'] ?? 'OR-EXEMPT');
            $amountPaid = (float)($_POST['amount_paid'] ?? 150.00);
            $paymentDate = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');

            $stmtC = $pdo->prepare("INSERT INTO assistance_certificates (
                request_id, request_ref, farmer_id, cert_type, control_number,
                inspection_date, issue_date, expiry_date, municipality, place_of_issue,
                vet_name, vet_position, municipal_agriculturist,
                verified_species, verified_heads, purpose, origin, destination,
                value, transport_means, plate_number, shipper, shipper_address,
                or_number, amount_paid, payment_date, status, created_by, created_at
            ) VALUES (?, ?, ?, 'COI', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Issued', ?, NOW())
            ON DUPLICATE KEY UPDATE
                inspection_date = VALUES(inspection_date), issue_date = VALUES(issue_date),
                expiry_date = VALUES(expiry_date), municipality = VALUES(municipality),
                place_of_issue = VALUES(place_of_issue), vet_name = VALUES(vet_name),
                vet_position = VALUES(vet_position), municipal_agriculturist = VALUES(municipal_agriculturist),
                verified_species = VALUES(verified_species), verified_heads = VALUES(verified_heads),
                purpose = VALUES(purpose), origin = VALUES(origin), destination = VALUES(destination),
                value = VALUES(value), transport_means = VALUES(transport_means), plate_number = VALUES(plate_number),
                shipper = VALUES(shipper), shipper_address = VALUES(shipper_address),
                or_number = VALUES(or_number), amount_paid = VALUES(amount_paid), payment_date = VALUES(payment_date),
                updated_at = NOW()");

            $stmtC->execute([
                $reqId, $reqRow['reference_no'], $reqRow['farmer_id'], $ctrlNo,
                $inspectionDate, $issueDate, $expiryDate, ($officerMuni ?: 'Ragay'), $placeOfIssue,
                $vetName, $vetPos, $muniAgri,
                $verSpecies, $verHeads, $purpose, $origin, $dest,
                $val, $transMeans, $plate, $shipper, $shipperAddr,
                $orNumber, $amountPaid, $paymentDate, $user_id
            ]);

            $stCertOut = $pdo->prepare("SELECT ac.*, ar.reference_no as req_ref, ar.farmer_name, ar.assistance_type FROM assistance_certificates ac JOIN farmer_assistance_requests ar ON ac.request_id = ar.id WHERE ac.control_number = ? LIMIT 1");
            $stCertOut->execute([$ctrlNo]);
            $certSaved = $stCertOut->fetch(PDO::FETCH_ASSOC);

            $pdo->prepare("UPDATE farmer_assistance_requests SET status = 'Certificate Issued', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$reqId]);

            try {
                $pdo->prepare("INSERT INTO notifications(user_id, type, title, message, priority, is_read, created_at) VALUES(?, 'INFO', ?, ?, 'High', 0, NOW())")
                    ->execute([
                        $reqRow['farmer_id'],
                        "[CERTIFICATE ISSUED] Certificate of Inspection ({$ctrlNo})",
                        "Your Certificate of Inspection (COI) ({$ctrlNo}) has been issued by {$vetName} ({$officerMuni}). You can download and print your official certificate now."
                    ]);
            } catch (Throwable $eN) {}

            $pdo->commit();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => true, 'control_number' => $ctrlNo, 'cert' => $certSaved, 'message' => "Certificate of Inspection {$ctrlNo} officially encoded and issued!"]);
            exit;
        } elseif ($_POST['action'] === 'send_cert_to_farmer_portal') {
            if (!in_array($user_role, ['Admin', 'Agriculture Official'], true)) {
                throw new Exception('Unauthorized access.');
            }
            $certId = (int)($_POST['cert_id'] ?? 0);
            if ($certId <= 0) {
                throw new Exception('Invalid certificate ID.');
            }
            $stC = $pdo->prepare("SELECT ac.*, ar.farmer_name AS req_farmer_name FROM assistance_certificates ac LEFT JOIN farmer_assistance_requests ar ON ac.request_id = ar.id WHERE ac.id = ?");
            $stC->execute([$certId]);
            $certRow = $stC->fetch(PDO::FETCH_ASSOC);
            if (!$certRow) {
                throw new Exception('Certificate record not found.');
            }
            $pdo->prepare("UPDATE assistance_certificates SET status = 'Issued' WHERE id = ?")->execute([$certId]);
            if (!empty($certRow['request_id'])) {
                $pdo->prepare("UPDATE farmer_assistance_requests SET status = 'Certificate Issued', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([(int)$certRow['request_id']]);
            }
            $cTypeLabel = ($certRow['cert_type'] === 'VHC') ? 'Veterinary Health Certificate (VHC)' : 'Certificate of Inspection (COI)';
            $ctrlNo = $certRow['control_number'] ?? '';
            $vetNm = $certRow['vet_name'] ?? 'Municipal Veterinarian';
            if (!empty($certRow['farmer_id'])) {
                try {
                    $pdo->prepare("INSERT INTO notifications(user_id, type, title, message, priority, is_read, created_at) VALUES(?, 'INFO', ?, ?, 'High', 0, NOW())")
                        ->execute([
                            (int)$certRow['farmer_id'],
                            "[DIGITAL CERTIFICATE] {$cTypeLabel} ({$ctrlNo})",
                            "Your official {$cTypeLabel} ({$ctrlNo}) issued by {$vetNm} has been submitted to your Farmer Portal Digital Certificate Management. You can now view, print, or download PDF."
                        ]);
                } catch (Throwable $eN) {}
            }
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([
                'success' => true,
                'cert_id' => $certId,
                'control_number' => $ctrlNo,
                'message' => "{$cTypeLabel} ({$ctrlNo}) has been submitted to the Farmer Portal Digital Certificate Management!"
            ]);
            exit;
        } elseif ($_POST['action'] === 'issue_indemnity_certificate') {
            if (!in_array($user_role, ['Admin', 'Agriculture Official'], true)) {
                throw new Exception('Unauthorized access.');
            }
            $insId = !empty($_POST['insurance_id']) ? (int)$_POST['insurance_id'] : null;
            $farmerId = !empty($_POST['farmer_id']) ? (int)$_POST['farmer_id'] : null;
            $ctrlNo = trim($_POST['control_number'] ?? '');
            if (!$ctrlNo) {
                $ctrlNo = 'IND-' . strtoupper($officerMuni ?: 'RAG') . '-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            }
            $farmerName = trim($_POST['farmer_name'] ?? '');
            $farmerBrgy = trim($_POST['farmer_barangay'] ?? '');
            $animalKind = trim($_POST['animal_kind_gender'] ?? '');
            $dateOfLoss = trim($_POST['date_of_loss'] ?? '');
            $causeDeath = trim($_POST['cause_of_death'] ?? '');
            if (!$farmerName || !$animalKind || !$dateOfLoss || !$causeDeath) {
                throw new Exception('Farmer name, kind/gender of animal, date of loss, and cause of death are required.');
            }
            $reqBrgyCert = isset($_POST['req_brgy_cert']) ? 1 : 0;
            $w1Name = trim($_POST['witness1_name'] ?? '');
            $w1Cedula = trim($_POST['witness1_cedula'] ?? '');
            $w1Aff = isset($_POST['witness1_affidavit']) ? 1 : 0;
            $w1Id = isset($_POST['witness1_valid_id']) ? 1 : 0;
            $w1Same = isset($_POST['witness1_same_brgy_unrelated']) ? 1 : 0;
            $w2Name = trim($_POST['witness2_name'] ?? '');
            $w2Cedula = trim($_POST['witness2_cedula'] ?? '');
            $w2Aff = isset($_POST['witness2_affidavit']) ? 1 : 0;
            $w2Id = isset($_POST['witness2_valid_id']) ? 1 : 0;
            $w2Same = isset($_POST['witness2_same_brgy_unrelated']) ? 1 : 0;
            $reqOwnerAff = isset($_POST['req_owner_affidavit']) ? 1 : 0;
            $reqPhotos = isset($_POST['req_dead_animal_photos']) ? 1 : 0;

            if (!$reqBrgyCert || !$w1Name || !$w1Aff || !$w1Id || !$w1Same || !$w2Name || !$w2Aff || !$w2Id || !$w2Same || !$reqOwnerAff || !$reqPhotos) {
                throw new Exception('All Indemnity Claim requirements (Barangay Certification, 2 unrelated same-barangay witnesses with Cedula/Affidavit/Valid ID, Owner Affidavit, and Dead Animal Photos) must be verified before issuing the certificate.');
            }

            $estLoss = (float)($_POST['estimated_loss'] ?? 0);
            $photoNote = trim($_POST['photo_receiver_note'] ?? 'Submitted via Messenger / Official Portal');
            $vetName = trim($_POST['vet_name'] ?? '') ?: 'Dana Mar F. San Andres, DVM';
            $muniAgri = trim($_POST['municipal_agriculturist'] ?? '');
            $issueDate = !empty($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d');
            $remarks = trim($_POST['remarks'] ?? '');

            $stmtInd = $pdo->prepare("INSERT INTO indemnity_claim_certificates (
                insurance_id, farmer_id, control_number, farmer_name, farmer_barangay, municipality,
                animal_kind_gender, date_of_loss, cause_of_death, estimated_loss,
                req_brgy_cert, witness1_name, witness1_cedula, witness1_affidavit, witness1_valid_id, witness1_same_brgy_unrelated,
                witness2_name, witness2_cedula, witness2_affidavit, witness2_valid_id, witness2_same_brgy_unrelated,
                req_owner_affidavit, req_dead_animal_photos, photo_receiver_note,
                vet_name, municipal_agriculturist, issue_date, remarks, status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Issued', ?, NOW())");

            $stmtInd->execute([
                $insId, $farmerId, $ctrlNo, $farmerName, $farmerBrgy, ($officerMuni ?: 'Ragay'),
                $animalKind, $dateOfLoss, $causeDeath, $estLoss,
                $reqBrgyCert, $w1Name, $w1Cedula, $w1Aff, $w1Id, $w1Same,
                $w2Name, $w2Cedula, $w2Aff, $w2Id, $w2Same,
                $reqOwnerAff, $reqPhotos, $photoNote,
                $vetName, $muniAgri, $issueDate, $remarks, $user_id
            ]);
            $newIndId = (int)$pdo->lastInsertId();

            if ($insId) {
                $pdo->prepare("UPDATE insurance_assistance SET pcic_status = 'Claim Approved', claim_tracking_code = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$ctrlNo, $insId]);
            }
            if ($farmerId) {
                try {
                    $pdo->prepare("INSERT INTO notifications(user_id, type, title, message, priority, is_read, created_at) VALUES(?, 'INFO', ?, ?, 'High', 0, NOW())")
                        ->execute([
                            $farmerId,
                            "[INDEMNITY CERTIFICATE ISSUED] ({$ctrlNo})",
                            "Your Indemnity Claim Verification Certificate ({$ctrlNo}) for {$animalKind} has been issued after all requirements were verified."
                        ]);
                } catch (Throwable $eN) {}
            }

            $stFetchInd = $pdo->prepare("SELECT * FROM indemnity_claim_certificates WHERE id = ?");
            $stFetchInd->execute([$newIndId]);
            $indRow = $stFetchInd->fetch(PDO::FETCH_ASSOC);

            $pdo->commit();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => true, 'cert' => $indRow, 'message' => "Indemnity Claim Certificate {$ctrlNo} issued successfully!"]);
            exit;
        } elseif ($_POST['action'] === 'delete_assistance_request') {
            if (!in_array($user_role, ['Admin', 'Agriculture Official'], true)) {
                throw new Exception('Unauthorized access.');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid assistance request ID.');
            $pdo->prepare("DELETE FROM assistance_certificates WHERE request_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM farmer_assistance_requests WHERE id = ?")->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Assistance request deleted successfully!']);
            exit;
        } elseif ($_POST['action'] === 'get_assistance_request_details') {
            $id = (int)($_POST['request_id'] ?? 0);
            $st = $pdo->prepare("SELECT ar.*, u.firstName, u.lastName, u.email, u.mobile as user_mobile FROM farmer_assistance_requests ar LEFT JOIN users u ON ar.farmer_id = u.id WHERE ar.id = ?");
            $st->execute([$id]);
            $req = $st->fetch(PDO::FETCH_ASSOC);
            if (!$req) throw new Exception('Request not found.');

            $stC = $pdo->prepare("SELECT * FROM assistance_certificates WHERE request_id = ? ORDER BY id DESC");
            $stC->execute([$id]);
            $certs = $stC->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'request' => $req, 'certificates' => $certs]);
            exit;
        } elseif ($_POST['action'] === 'review_form_submission') {
            $token = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
                throw new Exception('Invalid security token. Please refresh the page and try again.');
            }
            if (!in_array($user_role, ['Admin', 'Agriculture Official'], true)) {
                throw new Exception('Unauthorized access.');
            }
            $formType = $_POST['form_type'] ?? '';
            $subId = (int)($_POST['submission_id'] ?? 0);
            $decision = strtolower(trim($_POST['decision'] ?? ''));
            if (!in_array($decision, ['accepted', 'declined'], true)) {
                throw new Exception('Invalid decision value.');
            }
            $remarks = trim($_POST['remarks'] ?? '');
            if ($decision === 'declined' && empty($remarks)) {
                throw new Exception('Official reason / remarks is required when declining a submission.');
            }

            $farmerId = 0;
            $formTitle = '';

            if ($formType === 'farm_registration') {
                $chk = $pdo->prepare("SELECT farmer_id, farm_id, farm_name FROM farm_registrations WHERE id = ?");
                $chk->execute([$subId]);
                $row = $chk->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new Exception('Farm registration record not found.');
                $farmerId = (int)$row['farmer_id'];
                $formTitle = "Farm Registration ('" . ($row['farm_name'] ?? 'Farm') . "')";

                $pdo->prepare("UPDATE farm_registrations SET status = ?, remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                    ->execute([$decision, $remarks ?: null, $user_id, $subId]);

                if (!empty($row['farm_id'])) {
                    $farmStatus = ($decision === 'accepted') ? 'Approved' : 'Rejected';
                    $pdo->prepare("UPDATE farms SET status = ?, rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?")
                        ->execute([$farmStatus, ($decision === 'declined' ? $remarks : null), ($decision === 'accepted' ? $user_id : null), $row['farm_id']]);
                }
            } elseif ($formType === 'rsbsa') {
                $chk = $pdo->prepare("SELECT farmer_id, reference_no FROM rsbsa_enrollments WHERE id = ?");
                $chk->execute([$subId]);
                $row = $chk->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new Exception('RSBSA enrollment record not found.');
                $farmerId = (int)$row['farmer_id'];
                $formTitle = "RSBSA Enrollment (" . ($row['reference_no'] ?? 'N/A') . ")";

                $pdo->prepare("UPDATE rsbsa_enrollments SET status = ?, remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                    ->execute([$decision, $remarks ?: null, $user_id, $subId]);
            } elseif ($formType === 'pcic') {
                $chk = $pdo->prepare("SELECT farmer_id, application_no FROM pcic_insurance_applications WHERE id = ?");
                $chk->execute([$subId]);
                $row = $chk->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new Exception('PCIC insurance application not found.');
                $farmerId = (int)$row['farmer_id'];
                $formTitle = "PCIC Livestock Insurance (" . ($row['application_no'] ?? 'N/A') . ")";

                $pdo->prepare("UPDATE pcic_insurance_applications SET status = ?, remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                    ->execute([$decision, $remarks ?: null, $user_id, $subId]);
            } else {
                throw new Exception('Unknown form type.');
            }

            // In-app notification to the farmer
            if ($farmerId > 0) {
                $notifTitle = "Form Submission " . ucfirst($decision) . ": " . $formTitle;
                $notifMsg = "Your submission for $formTitle has been " . $decision . " by the Department of Agriculture.";
                if ($remarks) {
                    $notifMsg .= " Reason/Remarks: " . $remarks;
                }
                $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, priority, is_read, created_at) VALUES (?, 'INFO', ?, ?, ?, 0, NOW())")
                    ->execute([$farmerId, $notifTitle, $notifMsg, ($decision === 'declined' ? 'High' : 'Normal')]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Form submission marked as " . ucfirst($decision) . " successfully!"]);
            exit;
        } elseif ($_POST['action'] === 'get_form_submission') {
            $formType = $_POST['form_type'] ?? '';
            $subId = (int)($_POST['submission_id'] ?? 0);
            $record = null;
            if ($formType === 'farm_registration') {
                $st = $pdo->prepare("SELECT fr.*, CONCAT_WS(' ', u.firstName, u.lastName) AS farmer_name, u.email AS farmer_email, u.mobile AS farmer_mobile FROM farm_registrations fr LEFT JOIN users u ON fr.farmer_id = u.id WHERE fr.id = ?");
                $st->execute([$subId]);
                $record = $st->fetch(PDO::FETCH_ASSOC);
            } elseif ($formType === 'rsbsa') {
                $st = $pdo->prepare("SELECT r.*, CONCAT_WS(' ', u.firstName, u.lastName) AS farmer_name, u.email AS farmer_email, u.mobile AS farmer_mobile FROM rsbsa_enrollments r LEFT JOIN users u ON r.farmer_id = u.id WHERE r.id = ?");
                $st->execute([$subId]);
                $record = $st->fetch(PDO::FETCH_ASSOC);
            } elseif ($formType === 'pcic') {
                $st = $pdo->prepare("SELECT p.*, CONCAT_WS(' ', u.firstName, u.lastName) AS farmer_name, u.email AS farmer_email, u.mobile AS farmer_mobile FROM pcic_insurance_applications p LEFT JOIN users u ON p.farmer_id = u.id WHERE p.id = ?");
                $st->execute([$subId]);
                $record = $st->fetch(PDO::FETCH_ASSOC);
            }
            if (!$record) throw new Exception('Record not found.');
            $record['parsed_form_data'] = !empty($record['form_data']) ? json_decode($record['form_data'], true) : [];
            echo json_encode(['success' => true, 'submission' => $record]);
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$stmt = $pdo->query("
    SELECT i.*, f.name as farm_name
    FROM incidents i
    LEFT JOIN farms f ON i.farmId = f.id
    ORDER BY i.priority DESC, i.createdAt DESC
");
$all_incidents = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM public_reports ORDER BY createdAt DESC");
$public_reports = $stmt->fetchAll();

// Public reports with GPS for map pins
try {
    $stmt = $pdo->query("SELECT id, reportType, description, contactPhone, status, latitude, longitude, createdAt FROM public_reports WHERE latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY createdAt DESC");
    $map_public_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $map_public_reports = [];
}

$stmt = $pdo->prepare("
    SELECT u.*, p.gov_id, p.department, p.position, p.office,
           p.assigned_region, p.municipality, p.province, p.profile_pix
    FROM users u
    LEFT JOIN officer_profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user_profile = $stmt->fetch();
if (!$user_profile) {
    $user_profile = ['firstName' => 'Official', 'lastName' => 'User', 'email' => '', 'mobile' => ''];
}
$user_profile['mobile'] = $user_profile['mobile'] ?? '';
$user_name    = $user_profile ? ($user_profile['firstName'] . ' ' . substr($user_profile['lastName'], 0, 1)) : 'G';
$user_initial = strtoupper(substr($user_name, 0, 1));
$myPixUrl = user_pix_url($user_profile['profile_pix'] ?? null);
$user_fullname= $user_profile['firstName'] ?? 'Guest User';

// -------------------------------- RESOLVE OFFICER MUNICIPALITY ---------------------------------------------
$lgus = ['Ragay', 'Sipocot', 'Lupi', 'Del Gallego', 'Cabusao'];
$officerMuni = '';
$rawMuni = trim($user_profile['municipality'] ?? '');
foreach ($lgus as $lgu) {
    if (stripos($rawMuni, $lgu) !== false) {
        $officerMuni = $lgu;
        break;
    }
}
if (!$officerMuni && !empty($_GET['municipality'])) {
    foreach ($lgus as $lgu) {
        if (strcasecmp($_GET['municipality'], $lgu) === 0) {
            $officerMuni = $lgu;
            break;
        }
    }
}
if (!$officerMuni && !empty($_SESSION['officer_municipality'])) {
    foreach ($lgus as $lgu) {
        if (strcasecmp($_SESSION['officer_municipality'], $lgu) === 0) {
            $officerMuni = $lgu;
            break;
        }
    }
}
if (!$officerMuni) {
    $officerMuni = 'Ragay';
}

$lguVets = [
    'Ragay'       => ['vet_name' => 'Dr. Maria Santos, DVM', 'position' => 'Municipal Veterinarian', 'office' => 'Ragay Municipal Agriculture & Veterinary Office', 'place' => 'Ragay, Camarines Sur'],
    'Lupi'        => ['vet_name' => 'Dr. Juan dela Cruz, DVM', 'position' => 'Municipal Veterinarian', 'office' => 'Lupi Municipal Agriculture & Veterinary Office', 'place' => 'Lupi, Camarines Sur'],
    'Sipocot'     => ['vet_name' => 'Dr. Antonio Reyes, DVM', 'position' => 'Municipal Veterinarian', 'office' => 'Sipocot Municipal Agriculture & Veterinary Office', 'place' => 'Sipocot, Camarines Sur'],
    'Del Gallego' => ['vet_name' => 'Dr. Elena Alcantara, DVM', 'position' => 'Municipal Veterinarian', 'office' => 'Del Gallego Municipal Agriculture & Veterinary Office', 'place' => 'Del Gallego, Camarines Sur'],
    'Cabusao'     => ['vet_name' => 'Dr. Roberto Villafuerte, DVM', 'position' => 'Municipal Veterinarian', 'office' => 'Cabusao Municipal Agriculture & Veterinary Office', 'place' => 'Cabusao, Camarines Sur'],
];
$currentLguVet = $lguVets[$officerMuni] ?? $lguVets['Ragay'];

// Spatial counts for officer's municipality
$muniFarmsCount = 0;
$muniLivestockCount = 0;
$muniIncidentsCount = 0;
if (!empty($all_farms)) {
    foreach ($all_farms as $f) {
        $fm = $f['pet_municipality'] ?? '';
        $fa = $f['address'] ?? '';
        if (strcasecmp($fm, $officerMuni) === 0 || stripos($fa, $officerMuni) !== false) {
            $muniFarmsCount++;
            $muniLivestockCount += (int)($f['total_livestock'] ?? 0);
        }
    }
}
if (!empty($all_incidents)) {
    foreach ($all_incidents as $inc) {
        $im = $inc['pet_municipality'] ?? '';
        $ia = $inc['address'] ?? '';
        if (strcasecmp($im, $officerMuni) === 0 || stripos($ia, $officerMuni) !== false) {
            $muniIncidentsCount++;
        }
    }
}

$analytics = [
    'livestock_by_type' => [],
    'regional_stats'    => [],
    'totals'            => ['total_farms' => 0, 'total_livestock' => 0, 'total_incidents' => 0, 'active_incidents' => 0],
];
try {
    $analytics['totals']['total_farms']      = $pdo->query("SELECT COUNT(*) FROM farms")->fetchColumn();
    $analytics['totals']['total_livestock']  = $pdo->query("SELECT COALESCE(SUM(qty), 0) FROM livestock")->fetchColumn();
    $analytics['totals']['total_incidents']  = $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
    $analytics['totals']['active_incidents'] = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')")->fetchColumn();
    $analytics['livestock_by_type'] = $pdo->query("
        SELECT type, SUM(qty) as total_qty, COUNT(DISTINCT farmId) as farm_count
        FROM livestock GROUP BY type ORDER BY total_qty DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $analytics['regional_stats'] = $pdo->query("
        SELECT COALESCE(op.assigned_region, 'General') as region,
               COUNT(DISTINCT f.id) as farm_count,
               COALESCE(SUM(l.qty), 0) as livestock_count
        FROM farms f
        LEFT JOIN officer_profiles op ON f.ownerId = op.user_id
        LEFT JOIN livestock l ON f.id = l.farmId
        GROUP BY region ORDER BY livestock_count DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }

// -- EXTRA ANALYTICS (farm status, incident status/priority, top farms) --
$an_extra = ['farm_status' => [], 'incident_status' => [], 'incident_priority' => [], 'top_farms' => []];
try {
    foreach ($pdo->query("SELECT status, COUNT(*) AS c FROM farms GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $an_extra['farm_status'][$r['status'] ?: 'Unknown'] = (int)$r['c'];
    }
    foreach ($pdo->query("SELECT status, COUNT(*) AS c FROM incidents GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $an_extra['incident_status'][$r['status'] ?: 'Unknown'] = (int)$r['c'];
    }
    foreach ($pdo->query("SELECT priority, COUNT(*) AS c FROM incidents GROUP BY priority")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $an_extra['incident_priority'][$r['priority'] ?: 'Unknown'] = (int)$r['c'];
    }
    $an_extra['top_farms'] = $pdo->query("
        SELECT f.id, f.name, f.status, COALESCE(SUM(l.qty), 0) AS heads
        FROM farms f LEFT JOIN livestock l ON l.farmId = f.id
        GROUP BY f.id, f.name, f.status
        ORDER BY heads DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }
// Form submissions for DA review
$farmRegSubmissions = [];
$rsbsaSubmissions = [];
$pcicSubmissions = [];

try {
    $farmRegSubmissions = $pdo->query("
        SELECT fr.*, CONCAT_WS(' ', u.firstName, u.lastName) AS farmer_name, u.email AS farmer_email, u.mobile AS farmer_mobile,
               CONCAT_WS(' ', r.firstName, r.lastName) AS reviewer_name
        FROM farm_registrations fr
        LEFT JOIN users u ON fr.farmer_id = u.id
        LEFT JOIN users r ON fr.reviewed_by = r.id
        ORDER BY fr.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Build unified map dataset combining ALL farms and ALL farm_registrations
$map_farms_and_registrations = [];
$muniCenterCoords = [
    'Cabusao'     => [13.7167, 123.1167],
    'Del Gallego' => [13.9111, 122.5858],
    'Lupi'        => [13.7844, 122.8686],
    'Ragay'       => [13.8189, 122.7911],
    'Sipocot'     => [13.7636, 122.9733],
];
$brgyCenterCoords = [
    'liboro'              => [13.826667, 122.734444],
    'poblacion iraya'     => [13.821443, 122.792363],
    'poblacion ilawod'    => [13.816800, 122.789500],
    'apad'                => [13.835200, 122.774100],
    'banga caves'         => [13.847500, 122.752000],
    'buenasuerte'         => [13.802500, 122.742000],
    'cale'                => [13.838000, 122.812000],
    'catabangan'          => [13.876000, 122.658000],
    'f. simeon'           => [13.812000, 122.765000],
    'godofredo reyes'     => [13.862000, 122.695000],
    'inandawa'            => [13.809000, 122.825000],
    'lower omon'          => [13.798000, 122.768000],
    'lower santa cruz'    => [13.841000, 122.728000],
    'panaytayan'          => [13.861000, 122.745000],
    'port junction norte' => [13.828000, 122.781000],
    'port junction sur'   => [13.823000, 122.782000],
    'tagbac'              => [13.854000, 122.789000],
    'south centro'        => [13.766000, 122.976000],
    'north centro'        => [13.770000, 122.975000],
    'impig'               => [13.758000, 122.968000],
    'tara'                => [13.782000, 122.952000],
    'colacling'           => [13.776000, 122.898000],
    'san pedro'           => [13.792000, 122.852000],
    'sinuknipan'          => [13.918000, 122.602000],
    'barcelonita'         => [13.726000, 123.128000],
];
$resolveMuni = function (?string $muni, ?string $addr) use ($muniCenterCoords): string {
    $haystack = strtolower(trim(($muni ?? '') . ' ' . ($addr ?? '')));
    foreach (array_keys($muniCenterCoords) as $m) {
        if (strpos($haystack, strtolower($m)) !== false) return $m;
    }
    return 'Ragay';
};
$resolveFallbackCoord = function (?string $brgy, ?string $addr, string $muni) use ($brgyCenterCoords, $muniCenterCoords): array {
    $haystack = strtolower(trim(($brgy ?? '') . ' ' . ($addr ?? '')));
    foreach ($brgyCenterCoords as $bk => $coords) {
        if (strpos($haystack, $bk) !== false) return $coords;
    }
    return $muniCenterCoords[$muni] ?? [13.8189, 122.7911];
};
$indexedFarmIds = [];
foreach ($all_farms as $fRow) {
    $fid = (int)($fRow['id'] ?? 0);
    $lat = is_numeric($fRow['latitude'] ?? null) ? (float)$fRow['latitude'] : 0.0;
    $lng = is_numeric($fRow['longitude'] ?? null) ? (float)$fRow['longitude'] : 0.0;
    $muni = $resolveMuni($fRow['pet_municipality'] ?? '', $fRow['address'] ?? '');
    if ($lat == 0.0 || $lng == 0.0) {
        $base = $resolveFallbackCoord($fRow['pet_barangay'] ?? '', $fRow['address'] ?? '', $muni);
        $angle = ($fid * 137.5) * M_PI / 180;
        $radius = 0.0004 + (($fid % 5) * 0.00015);
        $lat = round($base[0] + cos($angle) * $radius, 6);
        $lng = round($base[1] + sin($angle) * $radius, 6);
    }
    $fRow['latitude'] = $lat;
    $fRow['longitude'] = $lng;
    $fRow['municipality'] = $muni;
    $fRow['source'] = 'farm';
    $indexedFarmIds[$fid] = count($map_farms_and_registrations);
    $map_farms_and_registrations[] = $fRow;
}
foreach ($farmRegSubmissions as $fr) {
    $fd = json_decode($fr['form_data'] ?? '{}', true);
    if (!is_array($fd)) $fd = [];
    $linkedFarmId = !empty($fr['farm_id']) ? (int)$fr['farm_id'] : 0;
    $frLat = is_numeric($fd['farm_lat'] ?? ($fd['latitude'] ?? null)) ? (float)($fd['farm_lat'] ?? $fd['latitude']) : 0.0;
    $frLng = is_numeric($fd['farm_lng'] ?? ($fd['longitude'] ?? null)) ? (float)($fd['farm_lng'] ?? $fd['longitude']) : 0.0;
    if ($linkedFarmId > 0 && isset($indexedFarmIds[$linkedFarmId])) {
        if ($frLat != 0.0 && $frLng != 0.0) {
            $idx = $indexedFarmIds[$linkedFarmId];
            if (empty($all_farms[$idx]['latitude']) || empty($all_farms[$idx]['longitude'])) {
                $map_farms_and_registrations[$idx]['latitude'] = $frLat;
                $map_farms_and_registrations[$idx]['longitude'] = $frLng;
            }
        }
        continue;
    }
    $frId = (int)($fr['id'] ?? 0);
    $frAddr = trim((string)($fr['farm_address'] ?? ($fd['farm_address'] ?? '')));
    $frMuni = $resolveMuni($fd['pet_municipality'] ?? ($fd['municipality'] ?? ''), $frAddr);
    if ($frLat == 0.0 || $frLng == 0.0) {
        $base = $resolveFallbackCoord($fd['pet_barangay'] ?? ($fd['barangay'] ?? ''), $frAddr, $frMuni);
        $angle = (($frId + 50) * 137.5) * M_PI / 180;
        $radius = 0.0004 + (($frId % 5) * 0.00015);
        $frLat = round($base[0] + cos($angle) * $radius, 6);
        $frLng = round($base[1] + sin($angle) * $radius, 6);
    }
    $lvSum = 0;
    foreach (['cattle','swine','poultry','goat','carabao','horse','sheep','rabbit'] as $ak) {
        $lvSum += (int)($fd['lv_qty_' . $ak] ?? 0);
    }
    $rawSt = strtolower((string)($fr['status'] ?? 'pending'));
    $mappedStatus = $rawSt === 'accepted' ? 'Approved' : ($rawSt === 'declined' ? 'Rejected' : 'Pending');
    $ownerParts = explode(' ', trim((string)($fr['farmer_name'] ?? '')), 2);
    $map_farms_and_registrations[] = [
        'id' => 'reg_' . $frId,
        'registration_id' => $frId,
        'name' => trim((string)($fr['farm_name'] ?? ($fd['farm_name'] ?? 'Farm Registration #' . $frId))),
        'ownerId' => (int)($fr['farmer_id'] ?? 0),
        'ownerName' => trim((string)($fr['farmer_name'] ?? trim(($fd['pet_firstname'] ?? '') . ' ' . ($fd['pet_surname'] ?? '')))),
        'firstName' => $ownerParts[0] ?? '',
        'lastName' => $ownerParts[1] ?? '',
        'type' => $fd['farm_type'] ?? ($fd['type'] ?? 'Mixed'),
        'address' => $frAddr,
        'pet_municipality' => $frMuni,
        'municipality' => $frMuni,
        'latitude' => $frLat,
        'longitude' => $frLng,
        'area' => (float)($fd['farm_area'] ?? ($fd['area'] ?? 0)),
        'status' => $mappedStatus,
        'total_livestock' => $lvSum,
        'source' => 'farm_registration',
    ];
    if (strcasecmp($frMuni, $officerMuni) === 0 || stripos($frAddr, $officerMuni) !== false) {
        $muniFarmsCount++;
        $muniLivestockCount += $lvSum;
    }
}

try {
    $rsbsaSubmissions = $pdo->query("
        SELECT r.*, CONCAT_WS(' ', u.firstName, u.lastName) AS farmer_name, u.email AS farmer_email, u.mobile AS farmer_mobile,
               CONCAT_WS(' ', rev.firstName, rev.lastName) AS reviewer_name
        FROM rsbsa_enrollments r
        LEFT JOIN users u ON r.farmer_id = u.id
        LEFT JOIN users rev ON r.reviewed_by = rev.id
        ORDER BY r.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

try {
    $pcicSubmissions = $pdo->query("
        SELECT p.*, CONCAT_WS(' ', u.firstName, u.lastName) AS farmer_name, u.email AS farmer_email, u.mobile AS farmer_mobile,
               CONCAT_WS(' ', rev.firstName, rev.lastName) AS reviewer_name
        FROM pcic_insurance_applications p
        LEFT JOIN users u ON p.farmer_id = u.id
        LEFT JOIN users rev ON p.reviewed_by = rev.id
        ORDER BY p.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Clean icons and theme colors for animal types
$type_icons = [
  'Cattle'  => '<i class="bi bi-shield-shaded"></i>',
  'Swine'   => '<i class="bi bi-heart-pulse-fill"></i>',
  'Poultry' => '<i class="bi bi-feather"></i>',
  'Goat'    => '<i class="bi bi-geo-fill"></i>',
  'Sheep'   => '<i class="bi bi-cloud-fill"></i>',
  'Horse'   => '<i class="bi bi-lightning-charge-fill"></i>',
  'Rabbit'  => '<i class="bi bi-stars"></i>',
  'Carabao' => '<i class="bi bi-water"></i>',
  'Buffalo' => '<i class="bi bi-water"></i>',
  'Other'   => '<i class="bi bi-tags-fill"></i>',
];
$type_emojis = $type_icons;
$type_colors = [
    'Cattle'  => '#3B82F6', 'Swine'   => '#F59E0B', 'Poultry' => '#EC4899',
    'Goat'    => '#8B5CF6', 'Sheep'   => '#10B981',  'Horse'   => '#F97316',
    'Rabbit'  => '#EF4444', 'Carabao' => '#6B7280',  'Buffalo' => '#6B7280',
    'Other'   => '#64748B',
];
$type_display = ['Buffalo' => 'Carabao'];

// -- YEARLY LIVESTOCK TREND (for line chart) --
$yearly_trend = [];
try {
  $trend_stmt = $pdo->query("
    SELECT
      YEAR(f.createdAt) as yr,
      l.type,
      COALESCE(SUM(l.qty),0) as total
    FROM livestock l
    JOIN farms f ON l.farmId = f.id
    WHERE YEAR(f.createdAt) >= YEAR(CURDATE()) - 5
    GROUP BY yr, l.type
    ORDER BY yr ASC, total DESC
  ");
  foreach ($trend_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $yearly_trend[$row['yr']][$row['type']] = (int)$row['total'];
  }
} catch (PDOException $e) { error_log($e->getMessage()); }
 
// -- ARCHIVE -- resolved/closed incidents & reports -----------------------------
$archived_incidents = $pdo->query("
  SELECT i.*, f.name as farm_name
  FROM incidents i
  LEFT JOIN farms f ON i.farmId = f.id
  WHERE i.status IN ('Resolved','Closed')
  ORDER BY i.resolvedAt DESC LIMIT 50
")->fetchAll();
 
$archived_reports = $pdo->query("
  SELECT * FROM public_reports
  WHERE status IN ('Resolved','Closed')
  ORDER BY createdAt DESC LIMIT 50
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>AgriTrace+ | Official Panel</title>
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <style>
    :root {
      --c-brand-dark: #1A4731; --c-brand-mid: #2D6A4F; --c-brand-accent: #10B981;
      --c-bg-page: #F0F4F2; --c-bg-card: #ffffff; --c-slate-50: #F1F5F9;
      --c-slate-100: #E2E8F0; --c-slate-200: #CBD5E1; --c-text-main: #1E293B;
      --c-text-sub: #64748B; --c-danger: #EF4444; --c-warning: #F59E0B;
      --c-success: #10B981; --sidebar-width: 280px; --topbar-height: 64px;
    }
    * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin: 0; padding: 0; }
    html {
      scroll-behavior: smooth;
      -webkit-text-size-adjust: 100%;
      text-size-adjust: 100%;
      overflow-x: hidden;
      width: 100%;
    }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--c-bg-page);
      color: var(--c-text-main);
      overflow-x: hidden;
      width: 100%;
      max-width: 100vw;
      -webkit-font-smoothing: antialiased;
    }
    h1, h2, h3, h4, h5, h6, p, span, a, label, strong, em, td, th {
      overflow-wrap: break-word;
      word-wrap: break-word;
    }

    /* SIDEBAR */
    .panel-sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; top: 0; left: calc(-1 * var(--sidebar-width)); background: var(--c-brand-dark); display: flex; flex-direction: column; transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); z-index: 1000; }
    .panel-sidebar.open { transform: translateX(var(--sidebar-width)); box-shadow: 10px 0 30px rgba(0,0,0,0.2); }
    .sidebar-header { padding: 0 24px; height: var(--topbar-height); display: flex; align-items: center; flex-shrink: 0; border-bottom: 1px solid rgba(255,255,255,0.08); }
    .sidebar-logo { font-family: 'DM Sans', sans-serif; font-size: 1.5rem; font-weight: 800; color: #fff; letter-spacing: -1px; }
    .sidebar-logo span { color: var(--c-brand-accent); }
    .sidebar-sub { font-size: 0.65rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; margin-top: 2px; }
    .panel-sidebar, .sidebar-nav, #sidebar { scrollbar-width: none !important; -ms-overflow-style: none !important; }
    .panel-sidebar::-webkit-scrollbar, .sidebar-nav::-webkit-scrollbar, #sidebar::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; background: transparent !important; -webkit-appearance: none !important; }
    .panel-sidebar::-webkit-scrollbar-thumb, .sidebar-nav::-webkit-scrollbar-thumb, #sidebar::-webkit-scrollbar-thumb { display: none !important; width: 0 !important; height: 0 !important; background: transparent !important; }
    .panel-sidebar::-webkit-scrollbar-track, .sidebar-nav::-webkit-scrollbar-track, #sidebar::-webkit-scrollbar-track { display: none !important; background: transparent !important; }
    .panel-sidebar::-webkit-scrollbar-button, .sidebar-nav::-webkit-scrollbar-button, #sidebar::-webkit-scrollbar-button { display: none !important; width: 0 !important; height: 0 !important; }
    .sidebar-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 14px 12px; scrollbar-width: none !important; -ms-overflow-style: none !important; }
    .nav-section-label { font-size: 0.65rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; padding: 10px 14px 4px; }
    .nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; margin-bottom: 3px; border-radius: 12px; color: rgba(255,255,255,0.92) !important; text-decoration: none; cursor: pointer; transition: all 0.18s; font-size: 0.88rem; font-weight: 600; outline: none; border: 1px solid transparent; user-select: none; }
    .nav-item i { font-size: 1.1rem; flex-shrink: 0; width: 20px; text-align: center; color: #10b981; transition: color 0.18s; }
    .nav-item:hover:not(.active) { background: rgba(255,255,255,0.08); color: #fff !important; transform: translateX(3px); }
    .nav-item:hover:not(.active) i { color: #34d399; }
    .nav-item.active { background: linear-gradient(135deg, #10b981, #059669); color: #fff !important; box-shadow: 0 8px 18px -8px rgba(16,185,129,.7); font-weight: 700; border-color: rgba(255,255,255,0.15); }
    .nav-item.active i { color: #ffffff !important; }

    /* DOCUMENT VERIFICATION DROPDOWN STYLES */
    .da-dropdown-btn {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 10px 14px;
      margin-bottom: 3px;
      border-radius: 12px;
      color: rgba(255, 255, 255, 0.92) !important;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.18s ease;
      font-size: 0.88rem;
      font-weight: 600;
      border: 1px solid transparent;
      user-select: none;
    }
    .da-dropdown-btn i {
      color: #10b981;
      font-size: 1.1rem;
      flex-shrink: 0;
      width: 20px;
      text-align: center;
    }
    .da-dropdown-btn:hover {
      background: rgba(255, 255, 255, 0.08);
      color: #ffffff !important;
      transform: translateX(3px);
    }
    .da-dropdown-btn:hover i {
      color: #34d399;
    }
    .da-dropdown-btn.open {
      background: rgba(16, 185, 129, 0.15);
      color: #ffffff !important;
      border-color: rgba(16, 185, 129, 0.3);
    }
    .da-drop-chevron {
      font-size: 0.8rem;
      color: rgba(255, 255, 255, 0.75);
      transition: transform 0.25s ease;
      margin-left: auto;
    }
    .da-dropdown-btn.open .da-drop-chevron {
      transform: rotate(180deg);
      color: #10b981;
    }
    .da-submenu {
      display: none;
      flex-direction: column;
      gap: 3px;
      padding: 4px 0 6px 10px;
      margin-bottom: 5px;
      border-left: 2px solid rgba(16, 185, 129, 0.35);
      margin-left: 18px;
    }
    .da-submenu.open {
      display: flex;
    }
    .da-subitem {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 12px;
      border-radius: 10px;
      color: rgba(255, 255, 255, 0.88) !important;
      font-size: 0.84rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.18s ease;
      border: 1px solid transparent;
      text-decoration: none;
      user-select: none;
    }
    .da-subitem i {
      color: #34d399;
      font-size: 1rem;
      width: 18px;
      text-align: center;
      flex-shrink: 0;
    }
    .da-subitem:hover {
      background: rgba(255, 255, 255, 0.1);
      color: #ffffff !important;
      transform: translateX(3px);
    }
    .da-subitem:hover i {
      color: #10b981;
    }
    .da-subitem.active {
      background: linear-gradient(135deg, #10b981, #059669);
      color: #ffffff !important;
      font-weight: 700;
      box-shadow: 0 4px 12px -3px rgba(16, 185, 129, 0.6);
      border-color: rgba(255, 255, 255, 0.2);
    }
    .da-subitem.active i {
      color: #ffffff !important;
    }
    .nav-divider { height: 1px; background: rgba(255,255,255,0.08); margin: 12px; }
    .nav-item.logout { color: rgba(239,68,68,0.8); }
    .nav-item.logout:hover { background: rgba(239,68,68,0.15); color: #EF4444; }
    .sidebar-footer { padding: 16px; background: rgba(0,0,0,0.2); border-top: 1px solid rgba(255,255,255,0.08); flex-shrink: 0; }
    .user-box { display: flex; align-items: center; gap: 12px; }
    .user-avatar { width: 40px; height: 40px; background: var(--c-brand-accent); color: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0; }
    .user-name { font-weight: 700; font-size: 0.88rem; color: #fff; }
    .user-role { font-size: 0.72rem; color: rgba(255,255,255,0.5); font-weight: 500; }

    /* TOPBAR */
    .topbar { position: fixed; top: 0; right: 0; left: 0; height: var(--topbar-height); background: var(--c-brand-dark); display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 900; }
    .topbar-title { font-family: 'DM Sans', sans-serif; font-size: 1rem; font-weight: 700; color: #fff; letter-spacing: 0.3px; }
    .topbar-right { display: flex; align-items: center; gap: 12px; }
    .topbar-avatar { width: 36px; height: 36px; background: var(--c-brand-accent); color: #fff; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; }
    .menu-btn { background: none; border: none; font-size: 1.5rem; color: #fff; cursor: pointer; display: flex; align-items: center; }
    .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
    .sidebar-overlay.open { display: block; }
    .main-content { padding: 24px 24px 32px; margin-top: var(--topbar-height); transition: margin-left 0.3s; }

    /* SECTIONS */
    .section { display: none; animation: fadeUp 0.35s ease; }
    .section.active { display: block; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

    .page-heading { margin-bottom: 20px; }
    .page-heading h1 { font-family: 'DM Sans', sans-serif; font-size: 1.5rem; font-weight: 800; color: var(--c-brand-dark); margin-bottom: 2px; }
    .page-heading p { font-size: 0.85rem; color: var(--c-text-sub); }

    .card { background: #fff; padding: 22px; border-radius: 16px; border: 1px solid var(--c-slate-100); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }

    /* STAT CARDS */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .stat-card { background: #fff; border-radius: 14px; border: 1px solid var(--c-slate-100); box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 18px 20px; display: flex; flex-direction: column; gap: 6px; }
    .stat-card .s-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 4px; }
    .stat-card .s-val {
  font-family: 'DM Sans', sans-serif;
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--c-brand-dark);
  line-height: 1;
  letter-spacing: -0.5px;
}
.map-stat-num {
  font-family: 'DM Sans', sans-serif;
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--c-brand-dark);
  line-height: 1;
  letter-spacing: -0.3px;
}
.at-val {
  font-family: 'DM Sans', sans-serif;
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--c-brand-dark);
  line-height: 1;
  letter-spacing: -0.5px;
}
 
/* -- ANIMAL CARDS (match farm-reg picker style) -- */
.animal-type-card {
  background: #fff;
  border: 1.5px solid var(--c-slate-100);
  border-radius: 14px;
  padding: 16px 12px 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
  cursor: default;
}
.animal-type-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.09);
  border-color: var(--c-brand-accent);
}
.animal-type-card .at-img {
  width: 56px;
  height: 56px;
  object-fit: contain;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.4rem;
  line-height: 1;
  background: #f0fdf4;
  border-radius: 50%;
  border: 2px solid #d1fae5;
}
.animal-type-card .at-count {
  font-family: 'DM Sans', sans-serif;
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--c-brand-dark);
  letter-spacing: -0.3px;
  line-height: 1;
}
.animal-type-card .at-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--c-text-sub);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.animal-total-card {
  background: linear-gradient(135deg, var(--c-brand-dark), var(--c-brand-mid));
  border-radius: 14px;
  padding: 16px 12px 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  box-shadow: 0 4px 14px rgba(26,71,49,0.2);
}
.animal-total-card .at-img { font-size: 2.4rem; line-height: 1; background: rgba(255,255,255,0.15); border-radius: 50%; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; border: 2px solid rgba(255,255,255,0.25); }
.animal-total-card .at-count { font-family: 'DM Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: #fff; letter-spacing: -0.3px; }
.animal-total-card .at-label { font-size: 0.72rem; font-weight: 700; color: rgba(255,255,255,0.75); text-transform: uppercase; letter-spacing: 0.5px; }
 
/* -- ARCHIVE TABLE -- */
.archive-badge { padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; background: #F1F5F9; color: #64748B; display: inline-block; }
 
/* -- FARM REVIEW MODAL & ALL DETAILS REVIEW MODALS (wide) -- */
#farmReviewModal .modal-content,
#daLivestockDetailsModal .modal-content,
#daVetRequestDetailsModal .modal-content,
#daSlaughterDetailsModal .modal-content,
#daInvestigateReportModal .modal-content,
#daIncidentDetailsModal .modal-content,
.modal-review-lg .modal-content { 
  max-width: 760px !important; 
}
.review-section { margin-bottom: 18px; }
.review-section h4 { font-size: 0.82rem; font-weight: 800; color: var(--c-brand-dark); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid var(--c-slate-200); display: flex; align-items: center; gap: 8px; }
.review-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.review-item { background: var(--c-slate-50); border-radius: 8px; padding: 8px 12px; }
.review-item .r-label { font-size: 0.7rem; color: var(--c-text-sub); font-weight: 600; margin-bottom: 2px; }
.review-item .r-value { font-size: 0.85rem; font-weight: 700; color: var(--c-text-main); }
.review-photo { width: 100%; border-radius: 10px; max-height: 200px; object-fit: cover; border: 2px solid var(--c-slate-100); margin-bottom: 12px; }
.review-livestock-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: var(--c-slate-50); border-radius: 8px; margin-bottom: 6px; }
 
/* -- LINE CHART -- */
.chart-canvas-wrap { position: relative; height: 320px; }
    .stat-card .s-lbl { font-size: 0.72rem; color: var(--c-text-sub); text-transform: uppercase; font-weight: 600; letter-spacing: 0.4px; }

    /* LIVESTOCK GRID */
    .livestock-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-bottom: 24px; }
    .livestock-type-card { background: #fff; border-radius: 14px; border: 1px solid var(--c-slate-100); box-shadow: 0 1px 4px rgba(0,0,0,0.05); padding: 16px 14px; display: flex; flex-direction: column; align-items: flex-start; transition: transform 0.2s, box-shadow 0.2s; }
    .livestock-type-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }

    /* ICON BOX */
    .lt-icon {
      width: 52px; height: 52px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 2.1rem; flex-shrink: 0; margin-bottom: 12px;
      background: var(--c-slate-50); line-height: 1;
      border: 1px solid var(--c-slate-100);
    }
    .lt-total-card .lt-icon { background: rgba(255,255,255,0.18); border: none; }

    .lt-info { width: 100%; }

    /* -- FIXED: livestock number  -  clean, readable, not oversized -- */
    .lt-info .lt-count {
      font-family: 'DM Sans', sans-serif;
      font-size: 1.3rem;
      font-weight: 700;
      line-height: 1.1;
      color: var(--c-text-main);
      letter-spacing: -0.1px;
    }
    .lt-info .lt-name { font-size: 0.78rem; color: var(--c-text-sub); font-weight: 500; margin-top: 4px; }

    .lt-total-card {
      background: linear-gradient(135deg, var(--c-brand-dark), var(--c-brand-mid));
      color: #fff; border-radius: 14px; padding: 16px 14px;
      display: flex; flex-direction: column; align-items: flex-start;
      box-shadow: 0 4px 14px rgba(26,71,49,0.2);
    }
    .lt-total-card .lt-count { font-family: 'DM Sans', sans-serif; font-size: 1.3rem; font-weight: 700; line-height: 1.1; color: #fff; letter-spacing: -0.1px; }
    .lt-total-card .lt-name { font-size: 0.78rem; color: rgba(255,255,255,0.75); font-weight: 500; margin-top: 4px; }

    /* TABLE */
    .table-container { background: #fff; border-radius: 16px; border: 1px solid var(--c-slate-100); box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; margin-bottom: 24px; }
    .table-header { background: linear-gradient(135deg, var(--c-brand-mid), var(--c-brand-dark)); color: white; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    .table-header h2 { margin: 0; font-size: 1.1rem; font-weight: 700; font-family: 'DM Sans', sans-serif; }
    .table-scroll { overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; table-layout: auto; }
    th { background: var(--c-slate-50); padding: 9px 12px; text-align: left; font-weight: 700; color: var(--c-text-main); font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 2px solid var(--c-slate-100); white-space: nowrap; }
    td { padding: 8px 12px; border-bottom: 1px solid var(--c-slate-200); vertical-align: middle; font-size: 0.82rem; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #F8FAFC; }

    /* BADGES */
    .status-badge { padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; display: inline-block; }
    .status-approved { background: #D1FAE5; color: #059669; }
    .status-pending { background: #FEF3C7; color: #D97706; }
    .status-rejected { background: #FEE2E2; color: #DC2626; }
    .status-reviewed { background: #DBEAFE; color: #2563EB; }
    .status-resolved { background: #D1FAE5; color: #059669; }
    .status-closed { background: #F1F5F9; color: #64748B; }
    .priority-low { background: #F0FDF4; color: #16A34A; }
    .priority-medium { background: #FEF3C7; color: #D97706; }
    .priority-high { background: #FFF3CD; color: #C05621; }
    .priority-critical { background: #FEE2E2; color: #DC2626; }

    /* BUTTONS */
    .btn { padding: 7px 14px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.82rem; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; font-family: inherit; }
    .btn-primary { background: var(--c-brand-accent); color: white; }
    .btn-primary:hover { background: #059669; transform: translateY(-1px); }
    .btn-danger { background: var(--c-danger); color: white; }
    .btn-danger:hover { background: #DC2626; }
    .btn-ghost { background: var(--c-slate-100); color: var(--c-text-main); }
    .btn-ghost:hover { background: var(--c-slate-200); }

    .priority-select, .status-select { padding: 4px 8px; border-radius: 8px; border: 1px solid var(--c-slate-200); font-size: 0.8rem; font-weight: 600; font-family: inherit; cursor: pointer; background: #fff; color: var(--c-text-main); transition: border-color 0.2s; }
    .priority-select:focus, .status-select:focus { outline: none; border-color: var(--c-brand-accent); }
    .search-box { padding: 9px 16px; border: 1.5px solid var(--c-slate-200); border-radius: 10px; font-size: 0.88rem; font-family: inherit; background: rgba(255,255,255,0.15); color: #fff; }
    .search-box::placeholder { color: rgba(255,255,255,0.6); }
    .search-box:focus { outline: none; border-color: rgba(255,255,255,0.5); }

    /* -- MAP CONTAINER & TOOLBAR (ADMIN GIS STYLE) -- */
    .section-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 20px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }
    .section-header h3 {
      font-size: 1.55rem;
      font-weight: 800;
      letter-spacing: -0.02em;
      color: var(--c-brand-dark);
      margin: 0;
    }
    .section-header p {
      font-size: 0.88rem;
      color: var(--c-text-sub);
      margin-top: 4px;
    }
    .btn-top {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 16px;
      border-radius: 10px;
      font-size: 0.84rem;
      font-weight: 700;
      text-decoration: none;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      font-family: inherit;
    }
    .btn-top-primary {
      background: linear-gradient(135deg, #10b981, #059669);
      color: #ffffff;
      border: 1px solid #059669;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
    }
    .btn-top-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
      color: #ffffff;
    }
    .pulse-live-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #10b981;
      box-shadow: 0 0 8px #10b981;
      animation: livePulse 2s infinite;
      display: inline-block;
    }
    @keyframes livePulse {
      0% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.6); opacity: 0.4; }
      100% { transform: scale(1); opacity: 1; }
    }
    .map-section-card {
      background: #ffffff;
      border: 1px solid var(--c-slate-100);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    }
    .map-top-toolbar {
      padding: 14px 20px;
      background: #f8fafc;
      border-bottom: 1px solid var(--c-slate-100);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
    }
    .map-frame-view {
      width: 100%;
      height: 640px;
      border: none;
      display: block;
      background: #f8fafc;
      overscroll-behavior: contain;
    }
    @media (max-width: 768px) {
      .map-frame-view {
        height: 480px;
      }
    }
    .map-stats-bar {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
      background: #ffffff;
      border-top: 1px solid var(--c-slate-100);
    }
    .map-stat-item {
      padding: 16px 18px;
      text-align: center;
      border-right: 1px solid var(--c-slate-100);
    }
    .map-stat-item:last-child {
      border-right: none;
    }
    .map-stat-num {
      font-family: 'DM Sans', sans-serif;
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--c-brand-dark);
      line-height: 1;
      letter-spacing: -0.3px;
    }
    .map-stat-lbl {
      font-size: 0.68rem;
      color: var(--c-text-sub);
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0.5px;
      margin-top: 5px;
    }

    /* ===== ANALYTICS  -  MODERN INSIGHTS DASHBOARD ===== */
    .an-hero { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; padding: 26px 30px; margin-bottom: 22px; border-radius: 20px; color: #fff; overflow: hidden; background: linear-gradient(120deg, #0F3D2A 0%, #1A4731 45%, #10B981 140%); box-shadow: 0 12px 32px rgba(16,185,129,0.22); }
    .an-hero::before { content: ''; position: absolute; right: -60px; top: -90px; width: 280px; height: 280px; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,0.18), transparent 70%); }
    .an-hero::after { content: ''; position: absolute; left: 38%; bottom: -120px; width: 260px; height: 260px; border-radius: 50%; background: radial-gradient(circle, rgba(16,185,129,0.35), transparent 70%); }
    .an-hero-text, .an-hero-actions { position: relative; z-index: 1; }
    .an-eyebrow { display: inline-flex; align-items: center; gap: 6px; font-size: 0.7rem; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; padding: 5px 12px; border-radius: 20px; background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.22); margin-bottom: 10px; }
    .an-hero h1 { font-size: 1.85rem; font-weight: 800; letter-spacing: -0.03em; line-height: 1.15; margin-bottom: 6px; color: #fff; }
    .an-hero p { font-size: 0.9rem; color: rgba(255,255,255,0.78); max-width: 520px; }
    .an-hero-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .an-date-chip { display: inline-flex; align-items: center; gap: 7px; font-size: 0.8rem; font-weight: 600; padding: 9px 14px; border-radius: 10px; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2); margin-right: 4px; }
    .an-btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 15px; border-radius: 10px; font-family: inherit; font-size: 0.82rem; font-weight: 700; cursor: pointer; transition: all .2s ease; border: 1px solid transparent; }
    .an-btn-primary { background: #fff; color: #0F3D2A; }
    .an-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(0,0,0,0.2); }
    .an-btn-ghost { background: rgba(255,255,255,0.12); color: #fff; border-color: rgba(255,255,255,0.28); }
    .an-btn-ghost:hover { background: rgba(255,255,255,0.24); transform: translateY(-2px); }

    .btn-secondary { background: #f8fafc; color: #1e293b; border: 1.5px solid #cbd5e1; font-weight: 600; padding: .35rem .75rem; border-radius: 8px; cursor: pointer; transition: all .15s; display: inline-flex; align-items: center; gap: 5px; }
    .btn-secondary:hover { background: #e2e8f0; color: #0f172a; border-color: #94a3b8; }
    .btn-pdf-download { background: #f0fdf4 !important; color: #065f46 !important; border: 1.5px solid #a7f3d0 !important; font-weight: 700 !important; display: inline-flex !important; align-items: center !important; gap: 4px !important; }
    .btn-pdf-download:hover { background: #dcfce7 !important; color: #047857 !important; border-color: #6ee7b7 !important; }

    .an-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 22px; }
    .an-kpi { position: relative; background: #fff; border: 1px solid var(--c-slate-100); border-radius: 18px; padding: 20px 22px; overflow: hidden; transition: transform .25s ease, box-shadow .25s ease; box-shadow: 0 2px 10px rgba(15,23,42,0.04); }
    .an-kpi::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--kc); }
    .an-kpi::after { content: ''; position: absolute; right: -40px; top: -40px; width: 130px; height: 130px; border-radius: 50%; background: var(--kb); opacity: .55; }
    .an-kpi:hover { transform: translateY(-4px); box-shadow: 0 14px 30px rgba(15,23,42,0.1); }
    .an-kpi > * { position: relative; z-index: 1; }
    .an-kpi-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
    .an-kpi-icon { width: 44px; height: 44px; border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: var(--kb); color: var(--kc); }
    .an-kpi-chip { display: inline-flex; align-items: center; gap: 5px; font-size: 0.68rem; font-weight: 700; padding: 4px 9px; border-radius: 20px; background: #fff; color: var(--kc); border: 1px solid var(--kb); }
    .an-kpi-val { font-size: 2.1rem; font-weight: 800; letter-spacing: -0.04em; line-height: 1; color: var(--c-text-main); }
    .an-kpi-lbl { margin-top: 6px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: var(--c-text-sub); }
    .an-kpi-foot { margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--c-slate-100); font-size: 0.78rem; color: var(--c-text-sub); font-weight: 500; }

    .an-grid { display: grid; gap: 18px; margin-bottom: 22px; }
    .an-grid-5-7 { grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); }
    .an-grid-7-5 { grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); }
    .an-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .an-card { background: #fff; border: 1px solid var(--c-slate-100); border-radius: 18px; padding: 22px; box-shadow: 0 2px 10px rgba(15,23,42,0.04); min-width: 0; }
    .an-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
    .an-card-head h3 { display: flex; align-items: center; gap: 9px; font-size: 1rem; font-weight: 800; letter-spacing: -0.01em; color: var(--c-brand-dark); }
    .an-card-head h3 i { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 9px; font-size: .9rem; background: #ECFDF5; color: var(--c-brand-accent); }
    .an-card-head p { margin: 4px 0 0 39px; font-size: 0.78rem; color: var(--c-text-sub); }

    .an-donut-wrap { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; justify-content: center; }
    .an-donut-canvas { position: relative; width: 220px; height: 220px; flex-shrink: 0; }
    .an-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
    .an-donut-center strong { font-size: 1.7rem; font-weight: 800; letter-spacing: -0.03em; color: var(--c-text-main); line-height: 1; }
    .an-donut-center span { font-size: 0.68rem; text-transform: uppercase; letter-spacing: .8px; color: var(--c-text-sub); font-weight: 700; margin-top: 4px; }
    .an-legend { flex: 1; min-width: 150px; display: flex; flex-direction: column; gap: 8px; }
    .an-legend-row { display: flex; align-items: center; gap: 9px; padding: 7px 10px; border-radius: 10px; background: #F8FAFC; font-size: 0.82rem; }
    .an-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .an-legend-name { flex: 1; font-weight: 600; color: var(--c-text-main); }
    .an-legend-pct { font-weight: 800; color: var(--c-text-main); }
    .an-trend-canvas { position: relative; height: 290px; }

    .an-rank-list { display: flex; flex-direction: column; gap: 14px; }
    .an-rank-row { display: flex; align-items: center; gap: 12px; }
    .an-rank-no { width: 22px; text-align: center; font-size: 0.8rem; font-weight: 800; color: #94A3B8; }
    .an-rank-emoji { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; }
    .an-rank-body { flex: 1; min-width: 0; }
    .an-rank-top { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin-bottom: 7px; flex-wrap: wrap; }
    .an-rank-top strong { font-size: 0.88rem; font-weight: 700; color: var(--c-text-main); }
    .an-rank-top span { font-size: 0.74rem; color: var(--c-text-sub); }
    .an-rank-top span b { color: var(--c-text-main); font-weight: 800; }
    .an-rank-share { min-width: 48px; text-align: right; font-size: 0.82rem; font-weight: 800; color: var(--c-text-main); }
    .an-bar-track { height: 9px; background: #F1F5F9; border-radius: 20px; overflow: hidden; }
    .an-bar-thin { height: 7px; }
    .an-bar-fill { height: 100%; border-radius: 20px; transition: width 1.1s cubic-bezier(0.22, 1, 0.36, 1); }

    .an-region-list { display: flex; flex-direction: column; gap: 12px; }
    .an-region { padding: 14px 16px; border-radius: 14px; background: #F8FAFC; border: 1px solid #F1F5F9; transition: border-color .2s, transform .2s; }
    .an-region:hover { border-color: #A7F3D0; transform: translateX(3px); }
    .an-region-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .an-region-name { display: flex; align-items: center; gap: 9px; font-weight: 700; font-size: 0.88rem; color: var(--c-text-main); }
    .an-region-pin { width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: #D1FAE5; color: #059669; font-size: .75rem; }
    .an-region-pct { font-weight: 800; font-size: 0.85rem; color: #059669; }
    .an-region-meta { display: flex; gap: 16px; margin-top: 9px; font-size: 0.74rem; color: var(--c-text-sub); }
    .an-region-meta i { margin-right: 4px; }

    .an-seg { display: flex; height: 12px; border-radius: 20px; overflow: hidden; background: #F1F5F9; gap: 2px; margin-bottom: 16px; }
    .an-seg span { display: block; height: 100%; transition: width 1s ease; }
    .an-stat-list { display: flex; flex-direction: column; gap: 4px; }
    .an-stat-row { display: flex; align-items: center; gap: 10px; padding: 9px 4px; border-bottom: 1px solid #F1F5F9; font-size: 0.84rem; }
    .an-stat-row:last-child { border-bottom: none; }
    .an-stat-name { flex: 1; font-weight: 600; color: var(--c-text-main); }
    .an-stat-row strong { font-weight: 800; color: var(--c-text-main); }
    .an-stat-row em { font-style: normal; min-width: 38px; text-align: right; font-size: 0.74rem; font-weight: 700; color: var(--c-text-sub); }
    .an-chips { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--c-slate-100); }
    .an-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; color: var(--c-text-sub); background: #F8FAFC; border: 1px solid #E2E8F0; }
    .an-chip i { width: 7px; height: 7px; border-radius: 50%; background: var(--cc); }
    .an-chip b { color: var(--c-text-main); font-weight: 800; }

    .an-top-list { display: flex; flex-direction: column; gap: 14px; }
    .an-top-row { display: flex; align-items: center; gap: 12px; }
    .an-medal { width: 30px; height: 30px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.82rem; flex-shrink: 0; background: #F1F5F9; color: #64748B; }
    .an-medal-1 { background: linear-gradient(135deg, #FDE68A, #F59E0B); color: #78350F; }
    .an-medal-2 { background: linear-gradient(135deg, #E2E8F0, #94A3B8); color: #1E293B; }
    .an-medal-3 { background: linear-gradient(135deg, #FDBA74, #EA580C); color: #7C2D12; }
    .an-top-body { flex: 1; min-width: 0; }
    .an-top-name { font-size: 0.85rem; font-weight: 700; color: var(--c-text-main); margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .an-top-val { font-size: 0.9rem; font-weight: 800; color: var(--c-text-main); }

    .an-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 36px 10px; color: var(--c-text-sub); text-align: center; }
    .an-empty i { font-size: 2rem; color: #CBD5E1; }
    .an-empty p { font-size: 0.85rem; }
    .an-empty-sm { padding: 18px 10px; }

    @media (max-width: 1180px) { .an-grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 960px) {
      .an-grid-5-7, .an-grid-7-5 { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 680px) {
      .an-hero { padding: 20px 18px; border-radius: 16px; }
      .an-hero h1 { font-size: 1.45rem; }
      .an-hero-actions { width: 100%; }
      .an-date-chip { width: 100%; justify-content: center; margin: 0 0 4px; }
      .an-btn { flex: 1; justify-content: center; }
      .an-grid-3 { grid-template-columns: minmax(0, 1fr); }
      .an-card { padding: 18px 16px; border-radius: 16px; }
      .an-kpi { padding: 18px; }
      .an-kpi-val { font-size: 1.8rem; }
      .an-donut-canvas { width: 190px; height: 190px; }
      .an-trend-canvas { height: 240px; }
      .an-rank-no { display: none; }
      .an-rank-top { flex-direction: column; gap: 2px; }
    }

    /* ===== DASHBOARD  -  MODERN OVERVIEW ===== */
    .an-kpis.dn-five { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; align-items: stretch; }
    @media (max-width: 1200px) {
      .an-kpis.dn-five { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 768px) {
      .an-kpis.dn-five { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    }
    @media (max-width: 480px) {
      .an-kpis.dn-five { grid-template-columns: 1fr; }
    }
    .an-kpi {
      position: relative;
      background: #fff;
      border: 1px solid var(--c-slate-100);
      border-radius: 18px;
      padding: 18px 20px;
      overflow: hidden;
      transition: transform .25s ease, box-shadow .25s ease;
      box-shadow: 0 4px 16px -2px rgba(15,23,42,0.06);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-height: 160px;
      height: 100%;
    }
    .an-kpi-mid { margin: 8px 0; }
    .an-kpi-val { font-size: 2rem; font-weight: 800; letter-spacing: -0.04em; line-height: 1.1; color: var(--c-text-main); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .an-kpi-lbl { margin-top: 5px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: var(--c-text-sub); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .an-kpi-foot { margin-top: auto; padding-top: 10px; border-top: 1px dashed var(--c-slate-100); font-size: 0.76rem; color: var(--c-text-sub); font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .an-hero h1 strong, .an-hero p strong { color: #fff; font-weight: 800; }
    .dn-total-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; background: #ECFDF5; border: 1px solid #A7F3D0; color: #047857; font-size: 0.78rem; font-weight: 800; white-space: nowrap; }

    .dn-type-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 14px; align-items: stretch; }
    .dn-type {
      position: relative;
      padding: 14px 14px 15px;
      border-radius: 16px;
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-height: 146px;
    }
    .dn-type::before { content: ''; position: absolute; left: 0; top: 0; right: 0; height: 3px; background: var(--tc); opacity: .85; }
    .dn-type:hover { transform: translateY(-3px); box-shadow: 0 10px 22px rgba(15,23,42,0.08); border-color: var(--tc); background: #fff; }
    .dn-type-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
    .dn-type-emoji { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; background: color-mix(in srgb, var(--tc) 14%, #fff); color: var(--tc); flex-shrink: 0; }
    .dn-type-share { font-size: 0.7rem; font-weight: 800; padding: 3px 7px; border-radius: 20px; background: #fff; color: var(--tc); border: 1px solid color-mix(in srgb, var(--tc) 28%, #fff); }
    .dn-type-count { font-size: 1.45rem; font-weight: 800; letter-spacing: -0.03em; color: var(--c-text-main); line-height: 1.1; }
    .dn-type-name { font-size: 0.78rem; font-weight: 700; color: #475569; margin: 3px 0 8px; text-transform: capitalize; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .dn-actions { display: flex; flex-direction: column; gap: 10px; }
    .dn-action { display: flex; align-items: center; gap: 13px; width: 100%; padding: 12px 14px; border-radius: 14px; background: #F8FAFC; border: 1px solid #F1F5F9; cursor: pointer; text-align: left; font-family: inherit; transition: all .2s ease; }
    .dn-action:hover { background: #fff; border-color: var(--ac); transform: translateX(4px); box-shadow: 0 8px 18px rgba(15,23,42,0.07); }
    .dn-action-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; background: var(--ab); color: var(--ac); flex-shrink: 0; }
    .dn-action-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .dn-action-body strong { font-size: 0.86rem; font-weight: 700; color: var(--c-text-main); }
    .dn-action-body small { font-size: 0.74rem; color: var(--c-text-sub); }
    .dn-action-count { min-width: 32px; height: 28px; padding: 0 10px; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; background: var(--ac); color: #fff; }
    .dn-action-count-soft { background: var(--ab); color: var(--ac); font-size: 0.85rem; }
    .dn-action-go { color: #CBD5E1; font-size: 0.8rem; transition: transform .2s, color .2s; }
    .dn-action:hover .dn-action-go { color: var(--ac); transform: translateX(3px); }

    .dn-table-card { padding-bottom: 8px; margin-bottom: 24px; }
    .dn-link-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 13px; border-radius: 10px; border: 1px solid #E2E8F0; background: #fff; color: var(--c-brand-mid); font-family: inherit; font-size: 0.78rem; font-weight: 700; cursor: pointer; transition: all .2s; white-space: nowrap; }
    .dn-link-btn:hover { background: #ECFDF5; border-color: #A7F3D0; color: #047857; }
    .dn-link-btn:hover i { transform: translateX(3px); }
    .dn-link-btn i { transition: transform .2s; }
    .dn-table { width: 100%; table-layout: auto; }
    .dn-table th { background: transparent; padding: 8px 10px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .7px; color: #94A3B8; border-bottom: 1px solid #F1F5F9; }
    .dn-table td { padding: 9px 10px; border-bottom: 1px solid #F1F5F9; font-size: 0.82rem; }
    .dn-table tr:hover td { background: #F8FAFC; }
    .dn-inc-title { font-weight: 700; font-size: 0.9rem; color: var(--c-text-main); margin-bottom: 3px; }
    .dn-inc-sub { font-size: 0.75rem; color: var(--c-text-sub); }
    .dn-inc-sub i { margin-right: 2px; }
    .dn-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 11px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; color: var(--pc); background: color-mix(in srgb, var(--pc) 11%, #fff); border: 1px solid color-mix(in srgb, var(--pc) 22%, #fff); white-space: nowrap; }
    .dn-pill i { width: 6px; height: 6px; border-radius: 50%; background: var(--pc); }
    .dn-date { font-size: 0.84rem; font-weight: 600; color: var(--c-text-main); }
    .dn-date-sub { font-size: 0.72rem; color: var(--c-text-sub); margin-top: 2px; }
    .dn-resolve { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 10px; border: none; background: linear-gradient(135deg, #10B981, #059669); color: #fff; font-family: inherit; font-size: 0.78rem; font-weight: 700; cursor: pointer; box-shadow: 0 2px 8px rgba(16,185,129,0.25); transition: all .2s; }
    .dn-resolve:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(16,185,129,0.35); }
    .dn-resolve:disabled { background: #E2E8F0; color: #64748B; box-shadow: none; cursor: default; transform: none; }
    .dn-done { display: inline-flex; align-items: center; gap: 6px; font-size: 0.78rem; font-weight: 700; color: #059669; }

    @media (max-width: 680px) {
      .an-kpis.dn-five { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
      .an-kpis.dn-five .an-kpi { padding: 16px 14px; }
      .an-kpis.dn-five .an-kpi-val { font-size: 1.6rem; }
      .an-kpis.dn-five .an-kpi-chip { display: none; }
      .dn-type-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .dn-action-go { display: none; }
    }

    /* ANALYTICS */
    .analytics-section { background: #fff; border-radius: 16px; border: 1px solid var(--c-slate-100); box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; margin-bottom: 24px; }
    .analytics-header { padding: 18px 22px; border-bottom: 1px solid var(--c-slate-100); display: flex; justify-content: space-between; align-items: center; }
    .analytics-header h3 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--c-brand-dark); display: flex; align-items: center; gap: 8px; font-family: 'DM Sans', sans-serif; }
    .analytics-header h3 i { color: var(--c-brand-accent); }
    .chart-wrap { padding: 22px; overflow-x: auto; }
    .bar-chart { display: flex; flex-direction: column; gap: 12px; min-width: 300px; }
    .bar-row { display: flex; align-items: center; gap: 12px; }
    .bar-label { width: 85px; font-size: 0.82rem; font-weight: 700; color: var(--c-text-main); text-align: right; flex-shrink: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bar-track { flex: 1; background: var(--c-slate-50); border-radius: 8px; height: 34px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 8px; transition: width 1s cubic-bezier(0.4,0,0.2,1); display: flex; align-items: center; padding-left: 12px; font-size: 0.78rem; font-weight: 700; color: #fff; white-space: nowrap; min-width: 44px; }
    .bar-count { width: 64px; font-size: 0.88rem; font-weight: 800; color: var(--c-text-main); text-align: right; flex-shrink: 0; }

    /* ANALYTICS TOTALS */
    .analytics-totals { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .at-card { background: #fff; border-radius: 14px; border: 1px solid var(--c-slate-100); padding: 18px 20px; display: flex; flex-direction: column; gap: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .at-card .at-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; margin-bottom: 6px; }
    .at-card .at-val { font-family: 'DM Sans', sans-serif; font-size: 1.9rem; font-weight: 800; color: var(--c-brand-dark); line-height: 1; letter-spacing: -1px; }
    .at-card .at-lbl { font-size: 0.72rem; color: var(--c-text-sub); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }

    /* ===== MODERN SAAS PROFILE CARD STYLES ===== */
    #sec-profile {
      display: none;
    }
    #sec-profile.active {
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
      background: linear-gradient(145deg, #064e3b 0%, #065f46 45%, #059669 100%);
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
    .da-preview-avatar {
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
      color: var(--c-text-sub);
      flex-shrink: 0;
    }
    .da-preview-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* MODAL */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(6px); z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 20px; opacity: 0; visibility: hidden; transition: all 0.3s; }
    .modal-overlay.active, .modal-overlay[style*="display: flex"], .modal-overlay[style*="display:flex"] { opacity: 1 !important; visibility: visible !important; }
    .modal-content { background: #fff; border-radius: 18px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.25); transform: scale(0.9) translateY(16px); transition: all 0.3s; }
    .modal-overlay.active .modal-content { transform: scale(1) translateY(0); }
    .modal-header { padding: 22px 26px 14px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--c-slate-100); }
    .modal-header h3 { margin: 0; font-size: 1.1rem; color: var(--c-brand-dark); font-family: 'DM Sans', sans-serif; }
    .modal-close { background: none; border: none; font-size: 1.4rem; color: var(--c-text-sub); cursor: pointer; padding: 4px 8px; border-radius: 8px; line-height: 1; }
    .modal-close:hover { background: var(--c-slate-100); }
    .modal-body { padding: 18px 26px; }
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-weight: 700; color: var(--c-text-main); margin-bottom: 6px; font-size: 0.88rem; }
    .form-input { width: 100%; padding: 11px 14px; border: 1.5px solid var(--c-slate-200); border-radius: 10px; font-size: 0.92rem; font-family: inherit; transition: all 0.2s; }
    .form-input:focus { outline: none; border-color: var(--c-brand-accent); box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }
    .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .modal-actions { padding: 14px 26px 22px; border-top: 1px solid var(--c-slate-100); display: flex; gap: 10px; justify-content: flex-end; }
    .btn-cancel { padding: 11px 18px; border-radius: 10px; background: var(--c-slate-100); color: var(--c-text-main); border: none; font-weight: 700; cursor: pointer; font-family: inherit; }
    .btn-save { padding: 11px 22px; border-radius: 10px; background: linear-gradient(135deg, var(--c-brand-accent), #059669); color: #fff; border: none; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; font-family: inherit; }

    /* LOADING */
    #loadingOverlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.85); display: none; align-items: center; justify-content: center; z-index: 3000; }
    .spinner { width: 42px; height: 42px; border: 3px solid var(--c-slate-200); border-top: 3px solid var(--c-brand-accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* -- AUTO-HIDE & FULL VIEW (Official DA Panel) -- */
    body.sidebar-autohide .panel-sidebar {
      left: calc(-1 * var(--sidebar-width)) !important;
      box-shadow: 20px 0 50px rgba(0,0,0,0.45) !important;
      transition: left 0.28s cubic-bezier(0.16, 1, 0.3, 1), transform 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
      z-index: 1000 !important;
    }

    body.sidebar-autohide .panel-sidebar.hover-open,
    body.sidebar-autohide .panel-sidebar:hover,
    body.sidebar-autohide .panel-sidebar.open {
      left: 0 !important;
      transform: none !important;
    }

    body.sidebar-autohide .main-content {
      margin-left: 0 !important;
      width: 100% !important;
      max-width: 100% !important;
      transition: margin-left 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    body.sidebar-autohide .topbar {
      left: 0 !important;
      width: 100% !important;
      transition: left 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .menu-btn {
      display: flex !important;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    /* Edge Hover Detection Zone */
    .da-hover-zone {
      display: none;
      position: fixed;
      left: 0;
      top: 0;
      bottom: 0;
      width: 20px;
      z-index: 998;
    }
    body.sidebar-autohide .da-hover-zone {
      display: block;
    }

    /* Floating Edge Trigger Tab */
    .da-edge-trigger {
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
    .da-edge-trigger:hover {
      width: 28px;
      background: #059669;
      color: #ffffff;
    }
    body.sidebar-autohide .da-edge-trigger {
      display: grid;
    }

    .btn-da-pin {
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
    .btn-da-pin:hover {
      background: rgba(255, 255, 255, 0.2);
      color: #fff;
    }
    body:not(.sidebar-autohide) .btn-da-pin {
      color: #34d399;
      background: rgba(52, 211, 153, 0.18);
      border-color: rgba(52, 211, 153, 0.35);
    }

    /* Mobile sidebar close button */
    .btn-sidebar-close {
      display: none;
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
    .btn-sidebar-close:active {
      background: rgba(255, 255, 255, 0.25);
      transform: scale(0.95);
    }

    /* Universal Table Touch Scroll Container */
    .an-table-wrap,
    .table-scroll,
    .table-responsive {
      width: 100%;
      max-width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      display: block;
    }

    /* Form Submissions Review Tabs */
    .da-review-tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 1rem;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      padding-bottom: 4px;
    }
    .da-review-tab {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 18px;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.88rem;
      color: #475569;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.2s ease;
      flex-shrink: 0;
    }
    .da-review-tab:hover {
      background: #f8fafc;
      color: #0f172a;
    }
    .da-review-tab.active {
      background: #059669;
      color: #ffffff;
      border-color: #059669;
      box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
    }
    .da-review-tab .tab-badge {
      background: rgba(0, 0, 0, 0.08);
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 0.75rem;
    }
    .da-review-tab.active .tab-badge {
      background: rgba(255, 255, 255, 0.25);
      color: #fff;
    }

    /* Modal Folio Viewport */
    .modal-folio-viewport {
      flex: 1;
      overflow: auto;
      -webkit-overflow-scrolling: touch;
      background: #475569;
      padding: 1rem;
      display: flex;
      justify-content: center;
    }

    /* ===== SHARED MODERN THEME (matches Admin & Farmer panels) ===== */
    :root { --c-brand-dark: #064e3b; --c-brand-mid: #059669; --c-brand-accent: #10b981; --c-bg-page: #f1f5f9; }
    body { background: radial-gradient(1200px 500px at 90% -10%, rgba(16,185,129,.10), transparent 60%), var(--c-bg-page); }
    .panel-sidebar { background: linear-gradient(180deg, #022c22 0%, #064e3b 100%); box-shadow: 20px 0 50px -20px rgba(0,0,0,.4); }
    .sidebar-header { border-bottom-color: rgba(255,255,255,.08); }
    .nav-item { border-radius: 14px; padding: 12px 16px; font-weight: 600; }
    .nav-item.active { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 8px 18px -8px rgba(16,185,129,.7); }
    .sidebar-footer { background: rgba(0,0,0,.18); }
    .user-avatar, .topbar-avatar { background: linear-gradient(135deg, #10b981, #065f46); border-radius: 50%; overflow: hidden; }
    .av-img{width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;}

    /* Base language switch button */
    .lang-switch-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 9999px;
      background: #ffffff;
      border: 1px solid #cbd5e1;
      font-size: 0.78rem;
      font-weight: 700;
      color: #0f172a;
      cursor: pointer;
      transition: all .18s ease;
      box-shadow: 0 1px 3px rgba(0,0,0,.06);
      flex-shrink: 0;
      touch-action: manipulation;
    }
    .lang-switch-btn:hover {
      background: #f0fdf4;
      border-color: var(--c-brand-accent);
      color: var(--c-brand-dark);
    }
    .lang-switch-btn:active {
      transform: scale(.96);
    }

    .topbar { background: rgba(255,255,255,.85); backdrop-filter: saturate(180%) blur(14px); -webkit-backdrop-filter: saturate(180%) blur(14px); border-bottom: 1px solid var(--c-slate-100); }
    .topbar-title { color: var(--c-text-main); font-size: 1.05rem; font-weight: 800; letter-spacing: -.01em; }
    .menu-btn { color: #064e3b; }
    .tb-date { display: inline-flex; align-items: center; gap: 6px; font-size: .78rem; font-weight: 600; color: var(--c-text-sub); padding: 7px 12px; border-radius: 999px; background: #fff; border: 1px solid var(--c-slate-100); }
    .tb-name { font-size: .82rem; font-weight: 700; color: var(--c-text-main); }
    .an-hero { border-radius: 26px; padding: 32px 36px;
      background: radial-gradient(circle at 85% 20%, rgba(52,211,153,.35), transparent 45%), linear-gradient(135deg, #022c22 0%, #065f46 55%, #059669 100%) !important;
      box-shadow: 0 22px 44px -18px rgba(5,150,105,.55); }
    .an-kpi, .an-card, .card, .stat-card { border-radius: 20px; box-shadow: 0 8px 24px -14px rgba(15,23,42,.18); }
    .an-card-head h3 { color: var(--c-text-main); }
    .page-heading h1 { color: #0f172a; }

    /* Folio Read-Only Sheet styling */
    .folio-read-sheet {
      width: 8.5in;
      max-width: 8.5in;
      min-height: 13in;
      background: #ffffff;
      color: #111111;
      padding: 0.35in 0.4in;
      margin: 0 auto;
      box-shadow: 0 4px 20px rgba(0,0,0,0.18);
      border-radius: 4px;
      position: relative;
      box-sizing: border-box;
      font-family: Arial, Calibri, 'Segoe UI', sans-serif;
      font-size: 8.5pt;
      line-height: 1.25;
    }
    @media (max-width: 850px) {
      .folio-read-sheet {
        min-width: 580px;
      }
    }

    /* ===== RESPONSIVE MEDIA QUERIES ===== */
    @media (min-width: 1024px) { 
      .panel-sidebar { left: 0; transition: left 0.28s cubic-bezier(0.16, 1, 0.3, 1); }
      .main-content { margin-left: var(--sidebar-width); transition: margin-left 0.28s cubic-bezier(0.16, 1, 0.3, 1); padding: 28px 32px 36px; }
      .topbar { left: var(--sidebar-width); transition: left 0.28s cubic-bezier(0.16, 1, 0.3, 1); padding: 0 28px; }
      .sidebar-overlay { display: none !important; }
    }
    @media (max-width: 1023px) { 
      body.sidebar-open {
        overflow: hidden !important;
      }
      .tb-date, .tb-name { display: none !important; }
      .topbar { left: 0 !important; width: 100% !important; padding: 0 12px; z-index: 1100 !important; }
      .main-content { margin-left: 0 !important; width: 100% !important; max-width: 100vw !important; padding: 16px 12px 28px; }

      /* Mobile Drawer Behavior */
      .panel-sidebar {
        width: 290px !important;
        max-width: 86vw !important;
        height: 100vh !important;
        height: 100dvh !important;
        left: -100% !important;
        transform: none !important;
        transition: left 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
        box-shadow: none;
        z-index: 1200 !important;
        overscroll-behavior-y: contain;
        touch-action: pan-y !important;
        display: flex !important;
        flex-direction: column !important;
        background: #022c22 !important;
        background: linear-gradient(180deg, #022c22 0%, #064e3b 100%) !important;
        opacity: 1 !important;
      }
      .panel-sidebar .sidebar-nav {
        flex: 1 1 auto !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch !important;
        overscroll-behavior-y: contain !important;
        padding: 14px 12px !important;
      }
      body.sidebar-autohide .panel-sidebar {
        left: -100% !important;
        transform: none !important;
      }
      .panel-sidebar.open,
      .panel-sidebar.hover-open,
      body.sidebar-autohide .panel-sidebar.open,
      body.sidebar-autohide .panel-sidebar.hover-open {
        left: 0 !important;
        transform: none !important;
        box-shadow: 10px 0 40px rgba(0,0,0,0.65) !important;
      }
      .sidebar-overlay {
        z-index: 1150 !important;
        background: rgba(15, 23, 42, 0.7) !important;
        backdrop-filter: blur(4px) !important;
        -webkit-backdrop-filter: blur(4px) !important;
      }
      .sidebar-header {
        justify-content: space-between !important;
        padding: 0 16px !important;
        background: #011f18 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12) !important;
      }
      .sidebar-footer {
        background: #011f18 !important;
        border-top: 1px solid rgba(255, 255, 255, 0.12) !important;
        padding: 14px 16px !important;
      }
      .btn-sidebar-close {
        display: flex !important;
        width: 38px !important;
        height: 38px !important;
        background: rgba(255, 255, 255, 0.14) !important;
        border: 1px solid rgba(255, 255, 255, 0.22) !important;
        color: #ffffff !important;
        border-radius: 50% !important;
        align-items: center !important;
        justify-content: center !important;
      }
      .btn-sidebar-close:active {
        background: rgba(255, 255, 255, 0.28) !important;
      }

      /* Disable desktop edge triggers & hover auto-open on mobile/touch */
      .da-hover-zone,
      .da-edge-trigger,
      .btn-da-pin {
        display: none !important;
        pointer-events: none !important;
      }

      /* Mobile Topbar Menu Toggle Button (Solid, Visible, Tactile) */
      .menu-btn {
        min-width: 42px;
        min-height: 42px;
        width: 42px;
        height: 42px;
        font-size: 1.45rem;
        cursor: pointer !important;
        touch-action: manipulation;
        display: flex !important;
        align-items: center;
        justify-content: center;
        background: #ffffff !important;
        border: 1px solid var(--c-slate-200) !important;
        border-radius: 10px !important;
        color: #064e3b !important;
        box-shadow: 0 2px 5px rgba(0,0,0,0.08) !important;
        flex-shrink: 0;
      }
      .menu-btn:active {
        background: #e2e8f0 !important;
        transform: scale(0.96);
      }

      /* Mobile Menu Buttons: Solid, Opaque, High-Contrast */
      .nav-item {
        min-height: 44px;
        cursor: pointer !important;
        touch-action: manipulation;
        background: #064e3b !important;
        border: 1px solid rgba(255, 255, 255, 0.18) !important;
        color: #ffffff !important;
        border-radius: 12px !important;
        margin-bottom: 8px !important;
        padding: 11px 14px !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
      }
      .nav-item i {
        color: #34d399 !important;
        font-size: 1.15rem !important;
      }
      .nav-item:active,
      .nav-item:hover {
        background: #046c4e !important;
        border-color: rgba(255, 255, 255, 0.3) !important;
        color: #ffffff !important;
      }
      .nav-item.active {
        background: linear-gradient(135deg, #10b981, #059669) !important;
        border-color: #34d399 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.45) !important;
        font-weight: 700 !important;
      }
      .nav-item.active i {
        color: #ffffff !important;
      }

      /* Mobile Dropdown Button: Solid & Opaque */
      .da-dropdown-btn {
        min-height: 44px;
        cursor: pointer !important;
        touch-action: manipulation;
        background: #064e3b !important;
        border: 1px solid rgba(255, 255, 255, 0.18) !important;
        color: #ffffff !important;
        border-radius: 12px !important;
        margin-bottom: 8px !important;
        padding: 11px 14px !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
        display: flex !important;
        align-items: center !important;
      }
      .da-dropdown-btn i {
        color: #34d399 !important;
        font-size: 1.15rem !important;
      }
      .da-dropdown-btn:active,
      .da-dropdown-btn:hover {
        background: #046c4e !important;
        border-color: rgba(255, 255, 255, 0.3) !important;
        color: #ffffff !important;
      }
      .da-dropdown-btn.open {
        background: #055a42 !important;
        border-color: rgba(52, 211, 153, 0.5) !important;
        color: #ffffff !important;
      }

      /* Mobile Submenu & Subitems: Solid & Opaque */
      .da-submenu {
        background: rgba(0, 0, 0, 0.45) !important;
        border-left: 3px solid #10b981 !important;
        border-radius: 10px !important;
        padding: 8px 8px 8px 10px !important;
        margin: 4px 0 10px 10px !important;
        gap: 6px !important;
      }
      .da-subitem {
        min-height: 42px;
        cursor: pointer !important;
        touch-action: manipulation;
        background: #044333 !important;
        border: 1px solid rgba(255, 255, 255, 0.14) !important;
        color: #ffffff !important;
        border-radius: 10px !important;
        margin-bottom: 4px !important;
        padding: 10px 12px !important;
        display: flex !important;
        align-items: center !important;
      }
      .da-subitem i {
        color: #34d399 !important;
      }
      .da-subitem:active,
      .da-subitem:hover {
        background: #065f46 !important;
        border-color: rgba(255, 255, 255, 0.3) !important;
        color: #ffffff !important;
      }
      .da-subitem.active {
        background: linear-gradient(135deg, #10b981, #059669) !important;
        border-color: #34d399 !important;
        color: #ffffff !important;
        font-weight: 700 !important;
      }
      .da-subitem.active i {
        color: #ffffff !important;
      }

      /* Mobile Logout Button */
      .nav-item.logout {
        background: #7f1d1d !important;
        border: 1px solid #b91c1c !important;
        color: #ffffff !important;
      }
      .nav-item.logout i {
        color: #fca5a5 !important;
      }
      .nav-item.logout:active,
      .nav-item.logout:hover {
        background: #991b1b !important;
        border-color: #ef4444 !important;
        color: #ffffff !important;
      }

      /* Clean subtle mobile scrollbars for wide tables and modals */
      .an-table-wrap,
      .table-scroll,
      .table-responsive,
      .table-container,
      .modal-folio-viewport {
        scrollbar-width: thin;
        scrollbar-color: #94a3b8 #f1f5f9;
      }
      .an-table-wrap::-webkit-scrollbar,
      .table-scroll::-webkit-scrollbar,
      .table-responsive::-webkit-scrollbar,
      .table-container::-webkit-scrollbar,
      .modal-folio-viewport::-webkit-scrollbar {
        display: block !important;
        height: 5px !important;
        width: 5px !important;
      }
      .an-table-wrap::-webkit-scrollbar-track,
      .table-scroll::-webkit-scrollbar-track,
      .table-responsive::-webkit-scrollbar-track,
      .table-container::-webkit-scrollbar-track,
      .modal-folio-viewport::-webkit-scrollbar-track {
        background: #f1f5f9 !important;
        border-radius: 4px !important;
      }
      .an-table-wrap::-webkit-scrollbar-thumb,
      .table-scroll::-webkit-scrollbar-thumb,
      .table-responsive::-webkit-scrollbar-thumb,
      .table-container::-webkit-scrollbar-thumb,
      .modal-folio-viewport::-webkit-scrollbar-thumb {
        background: #94a3b8 !important;
        border-radius: 4px !important;
      }
    }
    @media (max-width: 768px) {
      :root { --topbar-height: 56px; }
      .main-content { padding: 14px 10px 24px; margin-top: 56px !important; }
      .topbar { padding: 0 10px; height: 56px !important; }
      .sidebar-header { height: 56px !important; }
      .topbar-title {
        font-size: clamp(0.9rem, 3.8vw, 1.05rem);
        max-width: none;
        flex: 1;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .topbar-right {
        gap: 8px !important;
      }
      .topbar-avatar {
        width: 34px !important;
        height: 34px !important;
        font-size: 0.85rem !important;
      }

      .page-heading { margin-bottom: 14px; }
      .page-heading h1 { font-size: 1.3rem; }
      .section-header { margin-bottom: 16px; gap: 10px; }
      .section-header h3 { font-size: 1.25rem; }
      .section-header p { font-size: 0.8rem; }

      .an-hero { padding: 18px 14px; border-radius: 18px; }
      .an-hero h1 { font-size: clamp(1.15rem, 4.2vw, 1.5rem); }
      .an-hero p { font-size: 0.82rem; }
      .an-hero-actions { width: 100%; display: flex; gap: 6px; flex-wrap: wrap; }
      .an-hero-actions .an-date-chip { width: 100%; justify-content: center; margin: 0 0 4px; font-size: 0.76rem; }
      .an-hero-actions .an-btn { flex: 1 1 auto; justify-content: center; font-size: 0.78rem; padding: 8px 10px; }

      /* KPI Cards: Responsive 2-column grid */
      .an-kpis,
      .an-kpis.dn-five {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px !important;
        margin-bottom: 18px !important;
      }
      .an-kpis .an-kpi,
      .an-kpis.dn-five .an-kpi {
        padding: 13px 11px !important;
        min-height: 128px !important;
        border-radius: 14px !important;
      }
      .an-kpis .an-kpi-icon,
      .an-kpis.dn-five .an-kpi-icon {
        width: 36px !important;
        height: 36px !important;
        font-size: 1rem !important;
        border-radius: 10px !important;
      }
      .an-kpis .an-kpi-chip,
      .an-kpis.dn-five .an-kpi-chip {
        font-size: 0.62rem !important;
        padding: 2px 6px !important;
      }
      .an-kpis .an-kpi-val,
      .an-kpis.dn-five .an-kpi-val { font-size: 1.45rem !important; }
      .an-kpis .an-kpi-lbl,
      .an-kpis.dn-five .an-kpi-lbl { font-size: 0.7rem !important; }
      .an-kpis .an-kpi-foot,
      .an-kpis.dn-five .an-kpi-foot { font-size: 0.68rem !important; margin-top: 6px !important; padding-top: 6px !important; }
      .an-kpis.dn-five .an-kpi:last-child { grid-column: span 2; }

      /* Animal type cards & Action center */
      .dn-type-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
      }
      .dn-type {
        padding: 10px 10px 12px !important;
        min-height: 120px !important;
        border-radius: 12px !important;
      }
      .dn-type-emoji {
        width: 32px !important;
        height: 32px !important;
        font-size: 1rem !important;
      }
      .dn-type-count {
        font-size: 1.25rem !important;
      }
      .dn-type-name {
        font-size: 0.74rem !important;
      }
      .dn-action {
        padding: 10px 12px !important;
        gap: 10px !important;
        border-radius: 12px !important;
      }
      .dn-action-icon {
        width: 36px !important;
        height: 36px !important;
        font-size: 0.95rem !important;
      }
      .dn-action-body strong {
        font-size: 0.82rem !important;
      }
      .dn-action-body small {
        font-size: 0.7rem !important;
      }
      .dn-action-go { display: none !important; }

      /* Universal touch-scrolling for ALL data tables */
      .an-table-wrap,
      .table-scroll,
      .table-responsive,
      .table-container,
      .card > div[style*="overflow"] {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        overscroll-behavior-x: contain !important;
        display: block !important;
        margin-bottom: 0.75rem !important;
      }
      .an-table-wrap table,
      .table-scroll table,
      .table-responsive table,
      .table-container table,
      table.data-table,
      table.an-table,
      table.dn-table,
      table#farmsTable,
      table#archiveIncTable,
      table#archiveRptTable {
        min-width: 640px !important;
        width: 100%;
      }

      /* Card headers wrap nicely on mobile */
      .an-card-head {
        flex-wrap: wrap !important;
        gap: 8px !important;
        margin-bottom: 14px !important;
      }

      /* Form Review tabs */
      .da-review-tabs {
        display: flex;
        gap: 6px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 6px;
        margin-bottom: 0.75rem;
        overscroll-behavior-x: contain;
      }
      .da-review-tab {
        padding: 8px 12px !important;
        font-size: 0.8rem !important;
        flex-shrink: 0;
      }

      /* Form Review filter bar on mobile */
      .da-review-filter-bar {
        flex-direction: column !important;
        align-items: stretch !important;
        padding: 12px 14px !important;
        gap: 10px !important;
      }
      .da-review-filter-bar > div:first-child {
        min-width: 0 !important;
        width: 100% !important;
      }
      .da-review-filter-bar > div:last-child {
        width: 100% !important;
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
      }
      .da-review-filter-bar select {
        flex: 1 !important;
        max-width: 70% !important;
      }

      /* Modals on mobile */
      .modal-overlay {
        padding: 8px !important;
        z-index: 9500 !important;
      }
      .modal-content,
      #farmReviewModal .modal-content,
      #editProfileModal .modal-content,
      #passwordModal .modal-content,
      #daViewFormModal > div,
      #daDeclineModal > div {
        width: 96vw !important;
        max-width: 96vw !important;
        max-height: 94vh !important;
        border-radius: 16px !important;
        margin: auto;
      }
      .modal-header { padding: 12px 14px !important; }
      .modal-body { padding: 12px 14px !important; }
      .modal-actions { padding: 10px 14px !important; flex-wrap: wrap; gap: 8px; }
      .modal-actions .btn,
      .modal-actions button,
      .modal-actions .btn-save,
      .modal-actions .btn-cancel {
        flex: 1 1 auto !important;
        justify-content: center !important;
        padding: 8px 14px !important;
      }

      /* View Form Modal (Folio) on mobile */
      #daViewFormModal > div > div:first-child {
        flex-wrap: wrap !important;
        gap: 8px !important;
        padding: 10px 12px !important;
      }
      #daViewFormModal > div > div:first-child > div:last-child {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 6px !important;
        width: 100% !important;
        justify-content: flex-end !important;
      }
      #daViewFormModal > div > div:first-child > div:last-child .btn {
        flex: 1 1 auto !important;
        justify-content: center !important;
        font-size: 0.76rem !important;
        padding: 6px 10px !important;
      }
      .modal-folio-viewport {
        justify-content: flex-start !important;
        padding: 0.5rem 0.25rem !important;
      }

      .form-row-2,
      .review-grid {
        grid-template-columns: 1fr !important;
      }
      .info-item { flex-direction: column; gap: 3px; }
      .info-value { text-align: left; }

      /* 16px font size on inputs prevents iOS zoom */
      input, select, textarea, .form-input, .form-control {
        font-size: 16px !important;
      }

      /* Map view iframe */
      .map-frame-view {
        height: 480px !important;
      }

      /* Analytics charts */
      .an-donut-wrap {
        flex-direction: column !important;
        align-items: center !important;
      }
      .an-legend {
        width: 100% !important;
      }
      .an-trend-canvas {
        height: 230px !important;
      }
    }

    @media (max-width: 480px) {
      .main-content { padding: 10px 8px 20px; }
      .topbar { padding: 0 8px; }
      .lang-switch-btn .lang-label { display: none; }
      .lang-switch-btn { padding: 6px 8px; }
      .card { padding: 14px 10px; }

      .an-kpis .an-kpi-val,
      .an-kpis.dn-five .an-kpi-val { font-size: 1.35rem !important; }
      .an-kpis .an-kpi-lbl,
      .an-kpis.dn-five .an-kpi-lbl { font-size: 0.68rem !important; }
      .an-kpis .an-kpi-foot,
      .an-kpis.dn-five .an-kpi-foot { font-size: 0.66rem !important; }

      .table-header {
        flex-direction: column !important;
        align-items: stretch !important;
        padding: 12px 14px !important;
      }
      .table-header .search-box {
        max-width: 100% !important;
        width: 100% !important;
      }
      .table-header > div:last-child {
        display: flex !important;
        flex-wrap: wrap !important;
        width: 100% !important;
        gap: 6px !important;
      }
      .table-header > div:last-child .btn {
        flex: 1 1 auto !important;
        justify-content: center !important;
        font-size: 0.72rem !important;
        padding: 6px 8px !important;
      }

      .page-heading h1 { font-size: 1.2rem; }
      .section-header h3 { font-size: 1.15rem; }

      /* Profile section on small screens */
      .profile-card-wrapper {
        margin: 0.5rem auto 1.5rem !important;
        border-radius: 18px !important;
      }
      .profile-card-header {
        padding: 24px 16px 20px !important;
      }
      .profile-avatar-circle {
        width: 68px !important;
        height: 68px !important;
      }
      .profile-card-body {
        padding: 18px 14px 22px !important;
      }
      .profile-card-actions {
        flex-direction: column !important;
        gap: 8px !important;
        margin-top: 20px !important;
      }
      .profile-btn-edit,
      .profile-btn-pw {
        width: 100% !important;
        justify-content: center !important;
        padding: 10px 16px !important;
      }
      .profile-info-row {
        padding: 8px 0 !important;
      }

      /* Map View Iframe */
      .map-frame-view {
        height: 420px !important;
      }
    }

    @media (max-width: 360px) {
      .an-kpis,
      .an-kpis.dn-five { grid-template-columns: 1fr !important; }
      .an-kpis.dn-five .an-kpi:last-child { grid-column: auto !important; }
      .dn-type-grid { grid-template-columns: 1fr !important; }
      .profile-info-row {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 2px !important;
      }
      .profile-info-value {
        max-width: 100% !important;
        text-align: left !important;
      }
    }
    
    /* ===== OFFICIAL FOLIO READ-ONLY VIEW (matches farmer print form) ===== */
.modal-folio-viewport{background:#475569;overflow:auto;-webkit-overflow-scrolling:touch;padding:1rem;display:flex;justify-content:flex-start}
.fo-sheet{width:8.5in;min-width:8.5in;max-width:8.5in;background:#fff;color:#111;padding:.35in .4in;margin:0 auto;box-sizing:border-box;
  font-family:Arial,Calibri,'Segoe UI',sans-serif;font-size:8.5pt;line-height:1.25;box-shadow:0 4px 20px rgba(0,0,0,.18);border-radius:4px}
.fo-sheet.pdf-mode{box-shadow:none;border-radius:0;margin:0;min-height:0}
.fo-sheet *{box-sizing:border-box}
.fo-row{display:flex;gap:3px;margin-top:2px}
.fo-cell{border:1px solid #000;padding:2px 4px;min-width:0}
.fo-bg{background:#FDECD2}
.fo-lbl{display:block;font-size:6.5pt;font-weight:800;text-transform:uppercase;color:#111;line-height:1.1}
.fo-val{display:block;font-size:8pt;font-weight:700;min-height:11px;word-break:break-word}
.fo-banner{background:#000;color:#fff;font-weight:900;font-size:7.5pt;padding:2px 5px;text-transform:uppercase;letter-spacing:.5px;margin-top:4px}
.fo-sec{background:#E6E6E6;font-size:8pt;font-weight:800;text-transform:uppercase;padding:2px 5px;border:1px solid #111;margin-top:4px}
.fo-sec.blue{background:#dbeafe;border-color:#93c5fd;color:#1e3a8a}
.fo-box{display:inline-block;width:10px;height:10px;border:1px solid #000;margin-right:3px;text-align:center;font-size:8px;line-height:9px;font-style:normal;font-weight:900;vertical-align:-1px;background:#fff}
.fo-chk{display:inline-flex;align-items:center;margin-right:8px;font-size:7.5pt;font-weight:600;white-space:nowrap}
.fo-tbl{width:100%;border-collapse:collapse;font-size:7pt}
.fo-tbl th,.fo-tbl td{border:1px solid #000;padding:2px 3px;vertical-align:middle;word-break:break-word}
.fo-tbl th{background:#000;color:#fff;font-weight:800;font-size:6.8pt;text-align:left}
.fo-tbl.light th{background:#f0f0f0;color:#111;text-align:center}
.fo-hdr{display:flex;justify-content:space-between;align-items:center;padding-bottom:4px}
.fo-logo{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:8px;font-weight:800;flex-shrink:0;border:1.5px solid}
.fo-sig{border-bottom:1px solid #000;padding-top:20px;text-align:center;font-weight:800;text-transform:uppercase;font-size:8pt}
.fo-note{font-size:6.8pt;font-style:italic;line-height:1.25;padding:2px 4px;border:1px solid #000;border-top:none}
.fo-stamp{display:inline-block;padding:1px 8px;border:2px solid;border-radius:4px;font-weight:900;text-transform:uppercase;font-size:8pt;letter-spacing:.5px}
.fo-foot{display:flex;justify-content:space-between;font-size:6.5pt;color:#555;margin-top:5px;border-top:1px solid #ddd;padding-top:2px}

/* PRINT: only the form */
#daPrintHost{display:none}
@page{size:8.5in 13in;margin:.3in}
@media print{
  body.da-printing > *:not(#daPrintHost){display:none !important}
  body.da-printing #daPrintHost{display:block !important}
  body.da-printing .fo-sheet{box-shadow:none !important;margin:0 !important;border-radius:0 !important;width:100% !important;min-width:0 !important;max-width:100% !important;padding:0 !important;
    page-break-inside:avoid;break-inside:avoid;-webkit-print-color-adjust:exact;print-color-adjust:exact}
}

/* Unified Assistance Sub-Tabs & Certificate Hub */
.da-subtabs {
  display: flex;
  gap: 8px;
  border-bottom: 2px solid #e2e8f0;
  margin-bottom: 1.25rem;
  padding-bottom: 2px;
  overflow-x: auto;
}
.da-subtab-btn {
  background: none;
  border: none;
  padding: 8px 16px;
  font-size: 0.88rem;
  font-weight: 700;
  color: #64748b;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all .15s ease;
  white-space: nowrap;
}
.da-subtab-btn:hover {
  color: #059669;
}
.da-subtab-btn.active {
  color: #059669;
  border-bottom-color: #059669;
}
.da-subtab-btn .badge {
  background: #f1f5f9;
  color: #475569;
  padding: 2px 7px;
  border-radius: 99px;
  font-size: 0.72rem;
  font-weight: 700;
}
.da-subtab-btn.active .badge {
  background: #ecfdf5;
  color: #065f46;
}

    </style>
</head>
<body class="sidebar-autohide">

<div class="da-hover-zone" id="daHoverZone" aria-hidden="true"></div>
<div class="da-edge-trigger" id="daEdgeTrigger" title="Hover or click for navigation">
  <i class="bi bi-chevron-right"></i>
</div>

<div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
<div id="loadingOverlay"><div class="spinner"></div></div>

<aside class="panel-sidebar" id="sidebar">
  <div class="sidebar-header">
    <div>
      <div class="sidebar-logo">Agri<span>Trace+</span></div>
      <div class="sidebar-sub">Official Portal</div>
    </div>
    <button type="button" class="btn-da-pin" id="daPinBtn" title="Pin / Unpin Sidebar">
      <i class="bi bi-pin-angle"></i>
    </button>
    <button type="button" class="btn-sidebar-close" onclick="closeSidebar()" aria-label="Close menu" title="Close Menu">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <div class="nav-item active" onclick="navTo('dashboard', this)"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></div>
    
    <div class="nav-section-label">Field Operations</div>
    <div class="nav-item" onclick="navTo('farmers', this)"><i class="bi bi-people-fill"></i><span>Farmer Management</span></div>
    <div class="nav-item" onclick="navTo('livestock', this)"><i class="bi bi-clipboard2-pulse-fill"></i><span>Livestock Monitoring</span></div>
    <div class="nav-item" onclick="navTo('health', this)"><i class="bi bi-heart-pulse-fill"></i><span>Health Monitoring</span></div>
    <!-- SERVICE REQUEST COLLAPSIBLE DROPDOWN -->
    <div class="da-dropdown-btn" id="btn-da-drop-services" onclick="toggleDaDropdown('da-drop-services')" aria-expanded="false">
      <span style="display:flex;align-items:center;gap:.65rem;"><i class="bi bi-clipboard2-check-fill"></i><span>Service Request</span></span>
      <i class="bi bi-chevron-down da-drop-chevron"></i>
    </div>
    <div class="da-submenu" id="da-drop-services">
      <div class="da-subitem" onclick="navTo('vet-requests', this)"><i class="bi bi-bandaid-fill"></i><span>Veterinary Requests</span></div>
      <div class="da-subitem" onclick="navTo('slaughter', this)"><i class="bi bi-receipt-cutoff"></i><span>Slaughter Monitoring</span></div>
    </div>
    
    <div class="nav-section-label">Biosecurity &amp; Regulatory</div>
    <div class="nav-item" onclick="navTo('outbreaks', this)"><i class="bi bi-shield-shaded"></i><span>Disease Surveillance</span></div>
    
    <!-- DOCUMENT VERIFICATION COLLAPSIBLE DROPDOWN -->
    <div class="da-dropdown-btn" id="btn-da-drop-doc" onclick="toggleDaDropdown('da-drop-doc')" aria-expanded="false">
      <span style="display:flex;align-items:center;gap:.65rem;"><i class="bi bi-patch-check-fill"></i><span>Document Verification</span></span>
      <i class="bi bi-chevron-down da-drop-chevron"></i>
    </div>
    <div class="da-submenu" id="da-drop-doc">
      <div class="da-subitem" onclick="navTo('farms', this)"><i class="bi bi-house-check-fill"></i><span>Farm Verification</span></div>
      <div class="da-subitem" onclick="navTo('insurance', this)"><i class="bi bi-shield-check"></i><span>Insurance Assistance</span></div>
      <div class="da-subitem" onclick="navTo('documents', this)"><i class="bi bi-file-earmark-check"></i><span>Digital Certificates</span></div>
      <div class="da-subitem" onclick="navTo('form-review', this)"><i class="bi bi-journal-check"></i><span>Form Submissions Review</span></div>
    </div>

    <div class="nav-item" onclick="navTo('public', this)"><i class="bi bi-file-earmark-text-fill"></i><span>Public Reports</span></div>
    <div class="nav-item" onclick="navTo('incidents', this)"><i class="bi bi-exclamation-triangle-fill"></i><span>All Incidents</span></div>
    
    <div class="nav-section-label">Spatial &amp; Intelligence</div>
    <div class="nav-item" onclick="navTo('map', this)"><i class="bi bi-geo-alt-fill"></i><span>Geo-Tagged Map</span></div>
    <div class="nav-item" onclick="navTo('reports', this)"><i class="bi bi-bar-chart-line-fill"></i><span>Analytics</span></div>
    
    <div class="nav-divider"></div>
    <div class="nav-item" onclick="navTo('profile', this)"><i class="bi bi-person-circle"></i><span>My Profile</span></div>
    <div class="nav-item logout" onclick="window.location.href='logout.php'"><i class="bi bi-power"></i><span>Logout</span></div>
  </nav>
  <div class="sidebar-footer">
    <div class="user-box" style="cursor:pointer;" onclick="navTo('profile', document.querySelector('.nav-item[onclick*=\'profile\']'))" title="View Profile">
      <div class="user-avatar"><?= avatar_inner($myPixUrl, $user_initial) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($user_fullname) ?></div>
        <div class="user-role"><?= htmlspecialchars($user_role) ?></div>
      </div>
    </div>
  </div>
</aside>

<header class="topbar">
  <div style="display:flex;align-items:center;gap:10px;min-width:0;flex:1;">
    <button type="button" class="menu-btn" onclick="toggleSidebar()" aria-label="Toggle Navigation Menu" title="Toggle Navigation"><i class="bi bi-list"></i></button>
    <span class="topbar-title" id="page-title">Dashboard</span>
  </div>
  <div class="topbar-right">
    <button type="button" class="lang-switch-btn" onclick="toggleLanguage()" title="Palitan ang Wika / Switch Language"><span class="lang-flag"><i class="bi bi-flag"></i></span> <span class="lang-label">Tagalog</span></button>
    <span class="tb-date"><i class="bi bi-calendar3"></i> <?= date('D, M j') ?></span><span class="tb-name"><?= htmlspecialchars($user_fullname) ?></span><div class="topbar-avatar"><?= avatar_inner($myPixUrl, $user_initial) ?></div>
  </div>
</header>

<main class="main-content">

  <!-- ===== DASHBOARD ===== -->
  <section id="sec-dashboard" class="section active">
  <?php
    $dn_hour      = (int)date('G');
    $dn_greet     = $dn_hour < 12 ? 'Good morning' : ($dn_hour < 18 ? 'Good afternoon' : 'Good evening');
    $dn_heads     = (int)$stats['total_livestock'];
    $dn_farm_cnt  = max(1, count($all_farms));
    $dn_appr_pct  = round(((int)$stats['approved_farms'] / $dn_farm_cnt) * 100);
    $dn_attention = (int)$stats['pending_farms'] + (int)$stats['pending_reports'] + (int)$stats['active_incidents'];
    $dn_max_type  = !empty($livestock_by_type) ? max(1, (int)max(array_column($livestock_by_type, 'total'))) : 1;
    $dn_status_clr = ['pending' => '#D97706', 'in_progress' => '#2563EB', 'resolved' => '#059669', 'closed' => '#64748B'];
    $dn_prio_clr   = ['low' => '#16A34A', 'medium' => '#D97706', 'high' => '#C2410C', 'critical' => '#DC2626'];
  ?>

  <!-- Hero -->
  <div class="an-hero">
    <div class="an-hero-text">
      <span class="an-eyebrow"><i class="bi bi-grid-1x2-fill"></i> Official Portal</span>
      <h1><?= $dn_greet ?>, <?= htmlspecialchars($user_fullname) ?></h1>
      <p><?= $dn_attention > 0
          ? 'You have <strong>' . number_format($dn_attention) . '</strong> item' . ($dn_attention === 1 ? '' : 's') . ' waiting for your attention today.'
          : 'Everything is up to date. Great work keeping the district running smoothly.' ?></p>
    </div>
    <div class="an-hero-actions">
      <span class="an-date-chip"><i class="bi bi-calendar3"></i> <?= date('l, F j, Y') ?></span>
      <button onclick="activateSection('farms')" class="an-btn an-btn-primary"><i class="bi bi-house-check-fill"></i> Review Farms</button>
      <button onclick="activateSection('map')" class="an-btn an-btn-ghost"><i class="bi bi-map-fill"></i> Open Map</button>
    </div>
  </div>

  <!-- KPI cards -->
  <div class="an-kpis dn-five">
    <div class="an-kpi" style="--kc:#10B981;--kb:#D1FAE5;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-house-check-fill"></i></div>
        <span class="an-kpi-chip"><?= $dn_appr_pct ?>%</span>
      </div>
      <div class="an-kpi-mid">
        <div class="an-kpi-val"><?= number_format($stats['approved_farms']) ?></div>
        <div class="an-kpi-lbl">Approved Farms</div>
      </div>
      <div class="an-kpi-foot">of <?= number_format(count($all_farms)) ?> registered</div>
    </div>
    <div class="an-kpi" style="--kc:#3B82F6;--kb:#DBEAFE;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-tags-fill"></i></div>
        <span class="an-kpi-chip"><?= count($livestock_by_type) . ' ' . (count($livestock_by_type) === 1 ? 'type' : 'types') ?></span>
      </div>
      <div class="an-kpi-mid">
        <div class="an-kpi-val"><?= number_format($dn_heads) ?></div>
        <div class="an-kpi-lbl">Total Livestock</div>
      </div>
      <div class="an-kpi-foot">Across all farms</div>
    </div>
    <div class="an-kpi" style="--kc:#F59E0B;--kb:#FEF3C7;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <span class="an-kpi-chip"><i class="bi bi-broadcast"></i> Live</span>
      </div>
      <div class="an-kpi-mid">
        <div class="an-kpi-val"><?= number_format($stats['active_incidents']) ?></div>
        <div class="an-kpi-lbl">Active Incidents</div>
      </div>
      <div class="an-kpi-foot">Pending &amp; in progress</div>
    </div>
    <div class="an-kpi" style="--kc:#EF4444;--kb:#FEE2E2;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
        <span class="an-kpi-chip">Public</span>
      </div>
      <div class="an-kpi-mid">
        <div class="an-kpi-val"><?= number_format($stats['pending_reports']) ?></div>
        <div class="an-kpi-lbl">Pending Reports</div>
      </div>
      <div class="an-kpi-foot">Awaiting review</div>
    </div>
    <div class="an-kpi" style="--kc:#8B5CF6;--kb:#EDE9FE;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-hourglass-split"></i></div>
        <span class="an-kpi-chip">Queue</span>
      </div>
      <div class="an-kpi-mid">
        <div class="an-kpi-val"><?= number_format($stats['pending_farms']) ?></div>
        <div class="an-kpi-lbl">Pending Farms</div>
      </div>
      <div class="an-kpi-foot">Awaiting approval</div>
    </div>
  </div>

  <!-- Livestock overview + Action center -->
  <div class="an-grid an-grid-7-5">
    <div class="an-card">
      <div class="an-card-head">
        <div><h3><i class="bi bi-diagram-3-fill"></i> Livestock Overview</h3><p>Heads registered per animal type</p></div>
        <span class="dn-total-pill"><i class="bi bi-layers-fill"></i> <?= number_format($dn_heads) ?> total</span>
      </div>
      <?php if (!empty($livestock_by_type)): ?>
      <div class="dn-type-grid">
        <?php foreach ($livestock_by_type as $lt):
          $t       = $lt['type'] ?: 'Other';
          $col     = $type_colors[$t] ?? '#64748B';
          $icon    = $type_icons[$t] ?? '<i class="bi bi-tags-fill"></i>';
          $display = $type_display[$t] ?? $t;
          $share   = $dn_heads > 0 ? round(($lt['total'] / $dn_heads) * 100, 1) : 0;
          $bar     = round(($lt['total'] / $dn_max_type) * 100);
        ?>
        <div class="dn-type" style="--tc:<?= $col ?>;">
          <div>
            <div class="dn-type-top">
              <span class="dn-type-emoji"><?= $icon ?></span>
              <span class="dn-type-share"><?= $share ?>%</span>
            </div>
            <div class="dn-type-count"><?= number_format($lt['total']) ?></div>
            <div class="dn-type-name"><?= htmlspecialchars($display) ?></div>
          </div>
          <div class="an-bar-track an-bar-thin"><div class="an-bar-fill" data-pct="<?= $bar ?>" style="width:0%;background:linear-gradient(90deg,<?= $col ?>,<?= $col ?>CC);"></div></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="an-empty"><i class="bi bi-inbox"></i><p>No livestock data recorded yet.</p></div>
      <?php endif; ?>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div><h3><i class="bi bi-lightning-charge-fill"></i> Action Center</h3><p>Jump straight to what needs you</p></div>
      </div>
      <div class="dn-actions">
        <button type="button" class="dn-action" style="--ac:#8B5CF6;--ab:#EDE9FE;" onclick="activateSection('farms')">
          <span class="dn-action-icon"><i class="bi bi-hourglass-split"></i></span>
          <span class="dn-action-body"><strong>Farms awaiting approval</strong><small>Review &amp; approve registrations</small></span>
          <span class="dn-action-count"><?= number_format($stats['pending_farms']) ?></span>
          <i class="bi bi-chevron-right dn-action-go"></i>
        </button>
        <button type="button" class="dn-action" style="--ac:#F59E0B;--ab:#FEF3C7;" onclick="activateSection('incidents')">
          <span class="dn-action-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
          <span class="dn-action-body"><strong>Active incidents</strong><small>Respond &amp; resolve alerts</small></span>
          <span class="dn-action-count"><?= number_format($stats['active_incidents']) ?></span>
          <i class="bi bi-chevron-right dn-action-go"></i>
        </button>
        <button type="button" class="dn-action" style="--ac:#EF4444;--ab:#FEE2E2;" onclick="activateSection('public')">
          <span class="dn-action-icon"><i class="bi bi-file-earmark-text-fill"></i></span>
          <span class="dn-action-body"><strong>Public reports</strong><small>Citizen reports to review</small></span>
          <span class="dn-action-count"><?= number_format($stats['pending_reports']) ?></span>
          <i class="bi bi-chevron-right dn-action-go"></i>
        </button>
        <button type="button" class="dn-action" style="--ac:#10B981;--ab:#D1FAE5;" onclick="activateSection('map')">
          <span class="dn-action-icon"><i class="bi bi-map-fill"></i></span>
          <span class="dn-action-body"><strong>Geo-Monitoring</strong><small>Live district map</small></span>
          <span class="dn-action-count dn-action-count-soft"><i class="bi bi-arrow-up-right"></i></span>
          <i class="bi bi-chevron-right dn-action-go"></i>
        </button>
        <button type="button" class="dn-action" style="--ac:#3B82F6;--ab:#DBEAFE;" onclick="activateSection('reports')">
          <span class="dn-action-icon"><i class="bi bi-bar-chart-line-fill"></i></span>
          <span class="dn-action-body"><strong>Analytics</strong><small>Trends &amp; insights</small></span>
          <span class="dn-action-count dn-action-count-soft"><i class="bi bi-arrow-up-right"></i></span>
          <i class="bi bi-chevron-right dn-action-go"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Recent incidents -->
  <div class="an-card dn-table-card">
    <div class="an-card-head">
      <div><h3><i class="bi bi-shield-exclamation"></i> Recent Incidents</h3><p>Latest 5 reports from the field</p></div>
      <button type="button" class="dn-link-btn" onclick="activateSection('incidents')">View all <i class="bi bi-arrow-right"></i></button>
    </div>
    <div class="table-scroll">
      <table class="dn-table">
        <thead><tr><th>Incident</th><th>Status</th><th>Priority</th><th>Reported</th><th style="text-align:right;">Action</th></tr></thead>
        <tbody>
          <?php foreach ($recent_incidents as $incident):
            $i_status_raw = (string)($incident['status'] ?? 'Pending');
            $i_status_key = strtolower(str_replace(' ', '_', $i_status_raw));
            $i_status_lbl = ucwords(str_replace('_', ' ', $i_status_key));
            $i_prio_raw   = (string)($incident['priority'] ?? 'Low');
            $i_prio_key   = strtolower($i_prio_raw);
            $i_reporter   = trim(($incident['firstName'] ?? '') . ' ' . ($incident['lastName'] ?? '')) ?: 'Unknown reporter';
            $i_open       = !in_array($i_status_key, ['resolved', 'closed'], true);
            $i_ts         = strtotime($incident['createdAt'] ?? 'now');
          ?>
          <tr id="dash-inc-<?= (int)$incident['id'] ?>">
            <td>
              <div class="dn-inc-title"><?= htmlspecialchars($incident['title'] ?? 'Untitled incident') ?></div>
              <div class="dn-inc-sub"><i class="bi bi-house-door"></i> <?= htmlspecialchars($incident['farm_name'] ?? 'N/A') ?> &middot; <i class="bi bi-person"></i> <?= htmlspecialchars($i_reporter) ?></div>
            </td>
            <td class="dash-status"><span class="dn-pill" style="--pc:<?= $dn_status_clr[$i_status_key] ?? '#64748B' ?>;"><i></i><?= htmlspecialchars($i_status_lbl) ?></span></td>
            <td><span class="dn-pill" style="--pc:<?= $dn_prio_clr[$i_prio_key] ?? '#64748B' ?>;"><i></i><?= htmlspecialchars(ucfirst($i_prio_raw)) ?></span></td>
            <td><div class="dn-date"><?= date('M j, Y', $i_ts) ?></div><div class="dn-date-sub"><?= date('g:i A', $i_ts) ?></div></td>
            <td style="text-align:right;">
              <?php if ($i_open): ?>
              <button class="dn-resolve" onclick="dashResolve(<?= (int)$incident['id'] ?>, this)"><i class="bi bi-check2-circle"></i> Resolve</button>
              <?php else: ?>
              <span class="dn-done"><i class="bi bi-check-circle-fill"></i> Resolved</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recent_incidents)): ?>
          <tr><td colspan="5"><div class="an-empty an-empty-sm"><i class="bi bi-shield-check"></i><p>No recent incidents &mdash; all clear!</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 12 Portal Operations & Management Grid -->
  <div class="an-card" style="margin-top:1.5rem;">
    <div class="an-card-head">
      <div>
        <h3><i class="bi bi-grid-3x3-gap-fill"></i> Department of Agriculture  -  District Operations Hub</h3>
        <p>Comprehensive regulatory and field management modules for Camarines Sur 1st District</p>
      </div>
      <span class="an-kpi-chip" style="background:#d1fae5;color:#065f46;padding:.3rem .75rem;border-radius:99px;font-size:.78rem;font-weight:700;">
        12 Active Services
      </span>
    </div>

    <div class="da-district-hub" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;padding:.5rem 0;">
      <!-- Feature 1: Farmer Management -->
      <div onclick="activateSection('farmers')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:flex-start;gap:.85rem;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.06)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
        <div style="width:42px;height:42px;border-radius:10px;background:#ecfdf5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-people-fill"></i>
        </div>
        <div>
          <strong style="font-size:.92rem;color:#0f172a;display:block;">Farmer Management</strong>
          <span style="font-size:.78rem;color:#64748b;display:block;margin-top:.2rem;">Approve and manage farmer accounts</span>
          <span style="display:inline-block;margin-top:.45rem;font-size:.72rem;color:#059669;font-weight:700;"><i class="bi bi-arrow-right-short"></i> Manage <?= count($all_farmers) ?> farmers</span>
        </div>
      </div>

      <!-- Feature 2: Farm Verification -->
      <div onclick="activateSection('farms')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:flex-start;gap:.85rem;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.06)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
        <div style="width:42px;height:42px;border-radius:10px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-house-check-fill"></i>
        </div>
        <div>
          <strong style="font-size:.92rem;color:#0f172a;display:block;">Farm Verification</strong>
          <span style="font-size:.78rem;color:#64748b;display:block;margin-top:.2rem;">Verify registered farm locations</span>
          <span style="display:inline-block;margin-top:.45rem;font-size:.72rem;color:#2563eb;font-weight:700;"><i class="bi bi-arrow-right-short"></i> Review <?= number_format($stats['pending_farms']) ?> pending</span>
        </div>
      </div>

      <!-- Feature 3: Livestock Monitoring -->
      <div onclick="activateSection('livestock')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:flex-start;gap:.85rem;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.06)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
        <div style="width:42px;height:42px;border-radius:10px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-clipboard2-pulse-fill"></i>
        </div>
        <div>
          <strong style="font-size:.92rem;color:#0f172a;display:block;">Livestock Monitoring</strong>
          <span style="font-size:.78rem;color:#64748b;display:block;margin-top:.2rem;">Monitor registered animals</span>
          <span style="display:inline-block;margin-top:.45rem;font-size:.72rem;color:#d97706;font-weight:700;"><i class="bi bi-arrow-right-short"></i> <?= number_format($dn_heads) ?> heads tracked</span>
        </div>
      </div>

      <!-- Feature 4: Health Monitoring -->
      <div onclick="activateSection('health')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:flex-start;gap:.85rem;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.06)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
        <div style="width:42px;height:42px;border-radius:10px;background:#fdf2f8;color:#db2777;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-heart-pulse-fill"></i>
        </div>
        <div>
          <strong style="font-size:.92rem;color:#0f172a;display:block;">Health Monitoring</strong>
          <span style="font-size:.78rem;color:#64748b;display:block;margin-top:.2rem;">Review animal health records</span>
          <span style="display:inline-block;margin-top:.45rem;font-size:.72rem;color:#db2777;font-weight:700;"><i class="bi bi-arrow-right-short"></i> <?= count($all_health_records) ?> clinical logs</span>
        </div>
      </div>

      <!-- Feature 5: Veterinary Requests -->
      <div onclick="activateSection('vet-requests')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:flex-start;gap:.85rem;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.06)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
        <div style="width:42px;height:42px;border-radius:10px;background:#fef2f2;color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-bandaid-fill"></i>
        </div>
        <div>
          <strong style="font-size:.92rem;color:#0f172a;display:block;">Veterinary Requests</strong>
          <span style="font-size:.78rem;color:#64748b;display:block;margin-top:.2rem;">Assign and manage assistance requests</span>
          <span style="display:inline-block;margin-top:.45rem;font-size:.72rem;color:#dc2626;font-weight:700;"><i class="bi bi-arrow-right-short"></i> Dispatch assistance</span>
        </div>
      </div>

      <!-- Feature 6: Disease Surveillance -->
      <div onclick="activateSection('outbreaks')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:flex-start;gap:.85rem;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.06)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
        <div style="width:42px;height:42px;border-radius:10px;background:#fff1f2;color:#e11d48;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-shield-shaded"></i>
        </div>
        <div>
          <strong style="font-size:.92rem;color:#0f172a;display:block;">Disease Surveillance</strong>
          <span style="font-size:.78rem;color:#64748b;display:block;margin-top:.2rem;">Monitor suspected outbreaks</span>
          <span style="display:inline-block;margin-top:.45rem;font-size:.72rem;color:#e11d48;font-weight:700;"><i class="bi bi-arrow-right-short"></i> 1km / 7km BAI Rings</span>
        </div>
      </div>

      <!-- Feature 7: Slaughter Monitoring -->
      <div onclick="activateSection('slaughter')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:flex-start;gap:.85rem;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.06)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
        <div style="width:42px;height:42px;border-radius:10px;background:#f5f3ff;color:#7c3aed;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-receipt-cutoff"></i>
        </div>
        <div>
          <strong style="font-size:.92rem;color:#0f172a;display:block;">Slaughter Monitoring</strong>
          <span style="font-size:.78rem;color:#64748b;display:block;margin-top:.2rem;">Review slaughter declarations</span>
          <span style="display:inline-block;margin-top:.45rem;font-size:.72rem;color:#7c3aed;font-weight:700;"><i class="bi bi-arrow-right-short"></i> <?= count($all_slaughter) ?> declarations</span>
        </div>
      </div>

      <!-- Feature 8: Insurance Assistance -->
      <div onclick="activateSection('insurance')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:flex-start;gap:.85rem;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.06)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
        <div style="width:42px;height:42px;border-radius:10px;background:#f0fdf4;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-shield-check"></i>
        </div>
        <div>
          <strong style="font-size:.92rem;color:#0f172a;display:block;">Insurance Assistance</strong>
          <span style="font-size:.78rem;color:#64748b;display:block;margin-top:.2rem;">Review insurance referrals</span>
          <span style="display:inline-block;margin-top:.45rem;font-size:.72rem;color:#16a34a;font-weight:700;"><i class="bi bi-arrow-right-short"></i> Endorse to PCIC</span>
        </div>
      </div>

      <!-- Feature 9: Document Verification -->
      <div onclick="activateSection('documents')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:flex-start;gap:.85rem;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.06)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
        <div style="width:42px;height:42px;border-radius:10px;background:#ecfeff;color:#0891b2;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-patch-check-fill"></i>
        </div>
        <div>
          <strong style="font-size:.92rem;color:#0f172a;display:block;">Document Verification</strong>
          <span style="font-size:.78rem;color:#64748b;display:block;margin-top:.2rem;">Verify submitted certificates</span>
          <span style="display:inline-block;margin-top:.45rem;font-size:.72rem;color:#0891b2;font-weight:700;"><i class="bi bi-arrow-right-short"></i> <?= count($all_certificates) ?> certificates</span>
        </div>
      </div>

      <!-- Feature 10: Public Reports -->
      <div onclick="activateSection('public')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:flex-start;gap:.85rem;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.06)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
        <div style="width:42px;height:42px;border-radius:10px;background:#fff7ed;color:#ea580c;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-file-earmark-text-fill"></i>
        </div>
        <div>
          <strong style="font-size:.92rem;color:#0f172a;display:block;">Public Reports</strong>
          <span style="font-size:.78rem;color:#64748b;display:block;margin-top:.2rem;">Investigate submitted incidents</span>
          <span style="display:inline-block;margin-top:.45rem;font-size:.72rem;color:#ea580c;font-weight:700;"><i class="bi bi-arrow-right-short"></i> <?= number_format($stats['pending_reports']) ?> pending</span>
        </div>
      </div>

      <!-- Feature 11: Geo-Tagged Map -->
      <div onclick="activateSection('map')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:flex-start;gap:.85rem;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.06)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
        <div style="width:42px;height:42px;border-radius:10px;background:#f0fdf4;color:#15803d;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-geo-alt-fill"></i>
        </div>
        <div>
          <strong style="font-size:.92rem;color:#0f172a;display:block;">Geo-Tagged Map</strong>
          <span style="font-size:.78rem;color:#64748b;display:block;margin-top:.2rem;">View farms and incident locations</span>
          <span style="display:inline-block;margin-top:.45rem;font-size:.72rem;color:#15803d;font-weight:700;"><i class="bi bi-arrow-right-short"></i> GIS Live Tracking</span>
        </div>
      </div>

      <!-- Feature 12: Analytics -->
      <div onclick="activateSection('reports')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;cursor:pointer;transition:transform .15s,box-shadow .15s;display:flex;align-items:flex-start;gap:.85rem;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.06)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
        <div style="width:42px;height:42px;border-radius:10px;background:#f8fafc;color:#334155;border:1px solid #cbd5e1;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-bar-chart-line-fill"></i>
        </div>
        <div>
          <strong style="font-size:.92rem;color:#0f172a;display:block;">Analytics</strong>
          <span style="font-size:.78rem;color:#64748b;display:block;margin-top:.2rem;">Generate municipal livestock statistics</span>
          <span style="display:inline-block;margin-top:.45rem;font-size:.72rem;color:#334155;font-weight:700;"><i class="bi bi-arrow-right-short"></i> 5 Municipalities</span>
        </div>
      </div>
    </div>
  </div>
  </section>

  <!-- ===== FARM INSPECTION ===== -->
  <section id="sec-farms" class="section">
  <div class="page-heading">
    <h1>Farm Verification</h1>
    <p>Verify registered farm locations and ownership before approving or inspecting.</p>
  </div>
  <div class="table-container">
    <div class="table-header">
      <h2><i class="bi bi-house-check"></i> All Farms</h2>
      <input type="text" class="search-box" placeholder="Search farms..." onkeyup="searchFarms(this.value)" style="max-width:260px;">
    </div>
    <div class="table-scroll">
      <table id="farmsTable">
        <thead><tr><th>Farm Name</th><th>Owner</th><th>Farm Type</th><th>App Type</th><th>Ownership</th><th>Status</th><th>Livestock</th><th>Actions</th></tr></thead>
        <tbody id="farmsTableBody">
          <?php foreach ($all_farms as $farm): ?>
          <?php 
            $appType = !empty($farm['application_type']) ? $farm['application_type'] : 'New';
            $isRenewal = strtolower($appType) === 'renewal';
            $ownership = !empty($farm['ownership']) ? $farm['ownership'] : 'Private';
            $isGovt = strtolower($ownership) === 'govt' || strtolower($ownership) === 'government';
          ?>
          <tr data-farm-id="<?= $farm['id'] ?>">
            <td style="font-weight:600;"><?= htmlspecialchars($farm['name']) ?></td>
            <td><?= htmlspecialchars(($farm['firstName']??'').' '.($farm['lastName']??'')) ?></td>
            <td><?= ucfirst(str_replace('_',' ',$farm['type'])) ?></td>
            <td>
              <span class="status-badge" style="font-weight:700;font-size:0.75rem;padding:3px 8px;border-radius:6px;<?= $isRenewal ? 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;' : 'background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;' ?>">
                <i class="bi <?= $isRenewal ? 'bi-arrow-repeat' : 'bi-plus-circle' ?>"></i> <?= htmlspecialchars($appType) ?>
              </span>
            </td>
            <td>
              <span class="status-badge" style="font-weight:700;font-size:0.75rem;padding:3px 8px;border-radius:6px;<?= $isGovt ? 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;' : 'background:#f1f5f9;color:#334155;border:1px solid #e2e8f0;' ?>">
                <i class="bi <?= $isGovt ? 'bi-building' : 'bi-person' ?>"></i> <?= $isGovt ? 'Govt' : 'Private' ?>
              </span>
            </td>
            <td class="farm-status-cell"><span class="status-badge status-<?= strtolower($farm['status']) ?>"><?= ucfirst($farm['status']) ?></span></td>
            <td><?= number_format($farm['total_livestock'] ?? 0) ?> heads</td>
            <td class="farm-action-cell" style="display:flex;gap:6px;flex-wrap:wrap;">
              <!-- REVIEW button always shown -->
              <button class="btn btn-ghost" style="font-size:0.78rem;" onclick="openFarmReview(<?= $farm['id'] ?>)">
                <i class="bi bi-eye"></i> Review
              </button>
              <?php if ($farm['status'] === 'Pending'): ?>
              <button class="btn btn-primary" onclick="approveFarm(<?= $farm['id'] ?>)"><i class="bi bi-check-circle"></i> Approve</button>
              <button class="btn btn-danger" onclick="rejectFarm(<?= $farm['id'] ?>)"><i class="bi bi-x-circle"></i> Reject</button>
              <?php else: ?>
              <span style="color:var(--c-text-sub);font-size:0.82rem;font-weight:600;"><?= $farm['status'] === 'Approved' ? '<i class="bi bi-check-circle"></i> Approved' : '<i class="bi bi-x-circle"></i> Rejected' ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- FARM REVIEW MODAL -->
<div id="farmReviewModal" class="modal-overlay">
  <div class="modal-content" style="max-width:720px;">
    <div class="modal-header">
      <h3><i class="bi bi-clipboard2-check"></i> Farm Registration Review</h3>
      <button class="modal-close" onclick="closeFarmReview()">&times;</button>
    </div>
    <div class="modal-body" id="farmReviewBody">
      <div style="text-align:center;padding:40px;color:var(--c-text-sub);">Loading...</div>
    </div>
    <div class="modal-actions" id="farmReviewActions"></div>
  </div>
</div>

  <!-- ===== 1. FARMER MANAGEMENT ===== -->
  <section id="sec-farmers" class="section">
    <div class="section-header">
      <div>
        <h3>Farmer Management</h3>
        <p>Approve and manage farmer user accounts, verify credentials, and review farm ownership in Camarines Sur 1st District.</p>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center;">
        <span class="an-kpi-chip" style="background:#d1fae5;color:#065f46;padding:.35rem .75rem;border-radius:99px;font-size:.78rem;font-weight:700;">
          <i class="bi bi-people-fill"></i> <?= count($all_farmers) ?> Farmers Registered
        </span>
      </div>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div>
          <h3><i class="bi bi-person-check-fill"></i> Farmer Directory &amp; Account Approval</h3>
          <p>Review new farmer registrations and manage active user access</p>
        </div>
      </div>
      <div class="an-table-wrap">
        <table class="an-table">
          <thead>
            <tr>
              <th>Farmer Name</th>
              <th>Contact Info</th>
              <th>Farms Registered</th>
              <th>Livestock Total</th>
              <th>Date Joined</th>
              <th>Account Status</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($all_farmers)): ?>
              <tr><td colspan="7" style="text-align:center;padding:2rem;color:#64748b;">No farmers registered yet.</td></tr>
            <?php else: ?>
              <?php foreach($all_farmers as $fm): 
                $fName = trim(($fm['firstName'] ?? '') . ' ' . ($fm['lastName'] ?? ''));
                $isPending = ($fm['status'] ?? 'Active') === 'Pending';
                $stClr = $isPending ? '#f59e0b' : (($fm['status'] ?? '') === 'Active' ? '#10b981' : '#ef4444');
              ?>
              <tr>
                <td>
                  <strong style="color:#0f172a;display:block;"><?= htmlspecialchars($fName) ?></strong>
                  <small style="color:#64748b;">User ID #<?= $fm['id'] ?></small>
                </td>
                <td>
                  <div><i class="bi bi-envelope"></i> <?= htmlspecialchars($fm['email']) ?></div>
                  <div><i class="bi bi-telephone"></i> <?= htmlspecialchars($fm['mobile'] ?: ' - ') ?></div>
                </td>
                <td style="font-weight:700;color:#059669;"><?= (int)$fm['farm_count'] ?> Farm<?= (int)$fm['farm_count']!==1?'s':'' ?></td>
                <td style="font-weight:700;color:#2563eb;"><?= number_format((int)$fm['livestock_count']) ?> Head<?= (int)$fm['livestock_count']!==1?'s':'' ?></td>
                <td style="font-size:.8rem;color:#64748b;"><?= date('M d, Y', strtotime($fm['createdAt'])) ?></td>
                <td>
                  <span style="background:<?= $stClr ?>15;color:<?= $stClr ?>;padding:.2rem .55rem;border-radius:99px;font-size:.72rem;font-weight:700;border:1px solid <?= $stClr ?>40;">
                    <?= htmlspecialchars($fm['status'] ?: 'Active') ?>
                  </span>
                </td>
                <td style="text-align:right;">
                  <div style="display:inline-flex;gap:.35rem;">
                    <?php if($isPending): ?>
                      <button type="button" class="an-btn an-btn-primary" style="padding:.3rem .65rem;font-size:.76rem;" onclick="setFarmerStatus(<?= $fm['id'] ?>, 'Active')">
                        <i class="bi bi-check-lg"></i> Approve
                      </button>
                    <?php elseif($fm['status'] === 'Active'): ?>
                      <button type="button" class="an-btn an-btn-danger" style="padding:.3rem .65rem;font-size:.76rem;" onclick="setFarmerStatus(<?= $fm['id'] ?>, 'Inactive')">
                        <i class="bi bi-pause-fill"></i> Deactivate
                      </button>
                    <?php else: ?>
                      <button type="button" class="an-btn an-btn-primary" style="padding:.3rem .65rem;font-size:.76rem;" onclick="setFarmerStatus(<?= $fm['id'] ?>, 'Active')">
                        <i class="bi bi-play-fill"></i> Re-activate
                      </button>
                    <?php endif; ?>
                    <button type="button" class="an-btn" style="padding:.3rem .65rem;font-size:.76rem;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;" title="Permanently delete farmer account and records" onclick="deleteFarmer(<?= (int)$fm['id'] ?>, '<?= htmlspecialchars(addslashes($fName)) ?>')">
                      <i class="bi bi-trash3-fill"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ===== 2. LIVESTOCK MONITORING ===== -->
  <section id="sec-livestock" class="section">
    <div class="section-header">
      <div>
        <h3>Livestock Monitoring</h3>
        <p>Monitor registered animal herds, ear tags, breeds, and health conditions across all farms in the district.</p>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <span class="an-kpi-chip" style="background:#dbeafe;color:#1e40af;padding:.35rem .75rem;border-radius:99px;font-size:.78rem;font-weight:700;">
          <i class="bi bi-tags-fill"></i> <?= number_format($stats['total_livestock']) ?> Total Heads
        </span>
      </div>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div>
          <h3><i class="bi bi-clipboard2-pulse-fill"></i> Herd Inventory &amp; Tag Registry</h3>
          <p>Search by Ear Tag ID, species, breed, farm owner, or municipality</p>
        </div>
      </div>
      <div class="an-table-wrap">
        <table class="an-table">
          <thead>
            <tr>
              <th>Tag ID</th>
              <th>Species &amp; Breed</th>
              <th>Farm Facility</th>
              <th>Owner / Contact</th>
              <th>Qty</th>
              <th>Health Condition</th>
              <th>Vaccine Status</th>
              <th>Last Checkup</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($all_livestock)): ?>
              <tr><td colspan="9" style="text-align:center;padding:2rem;color:#64748b;">No livestock registered yet.</td></tr>
            <?php else: ?>
              <?php foreach($all_livestock as $lv): 
                $hSt = $lv['healthStatus'] ?? 'Healthy';
                $hClr = $hSt === 'Healthy' ? '#10b981' : ($hSt === 'Sick' ? '#ef4444' : ($hSt === 'Deceased' ? '#64748b' : '#3b82f6'));
              ?>
              <tr>
                <td><strong style="color:#0f172a;"><i class="bi bi-tag-fill" style="color:#059669;font-size:.78rem;"></i> <?= htmlspecialchars($lv['tagId']) ?></strong></td>
                <td>
                  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <strong><?= htmlspecialchars($lv['type']) ?></strong>
                    <span style="font-size:.68rem;font-weight:700;padding:1px 6px;border-radius:4px;<?= ($lv['farming_type'] ?? 'Backyard') === 'Commercial' ? 'background:#e0e7ff;color:#3730a3;' : 'background:#ecfdf5;color:#065f46;' ?>">
                      <?= htmlspecialchars($lv['farming_type'] ?? 'Backyard') ?>
                    </span>
                  </div>
                  <small style="display:block;color:#64748b;"><?= htmlspecialchars($lv['breed'] ?: 'Standard breed') ?></small>
                </td>
                <td>
                  <strong><?= htmlspecialchars($lv['farm_name']) ?></strong>
                  <small style="display:block;color:#64748b;"><?= htmlspecialchars($lv['pet_municipality'] ?? 'Camarines Sur') ?></small>
                </td>
                <td>
                  <div><?= htmlspecialchars(trim(($lv['firstName']??'').' '.($lv['lastName']??''))) ?></div>
                  <small style="color:#64748b;"><?= htmlspecialchars($lv['mobile'] ?: ' - ') ?></small>
                </td>
                <td style="font-weight:700;"><?= (int)($lv['qty'] ?: 1) ?></td>
                <td>
                  <span style="background:<?= $hClr ?>15;color:<?= $hClr ?>;padding:.15rem .5rem;border-radius:99px;font-size:.72rem;font-weight:700;border:1px solid <?= $hClr ?>40;">
                    <?= htmlspecialchars($hSt) ?>
                  </span>
                </td>
                <td>
                  <span style="color:#475569;font-size:.78rem;">
                    <i class="bi bi-shield-check" style="color:#059669;"></i> <?= htmlspecialchars($lv['vaccinationStatus'] ?: 'None') ?>
                  </span>
                </td>
                <td style="font-size:.78rem;color:#64748b;">
                  <?= !empty($lv['latest_checkup']) ? date('M d, Y', strtotime($lv['latest_checkup'])) : 'No checkup' ?>
                </td>
                <td style="text-align:right;">
                  <button type="button" class="an-btn" style="padding:.3rem .65rem;font-size:.76rem;background:#f8fafc;color:#1e293b;border:1px solid #cbd5e1;" onclick="viewLivestockDetails(<?= (int)$lv['id'] ?>)" title="View all livestock details">
                    <i class="bi bi-eye"></i> Details
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ===== 3. HEALTH MONITORING ===== -->
  <section id="sec-health" class="section">
    <div class="section-header">
      <div>
        <h3>Animal Health Monitoring</h3>
        <p>Review comprehensive clinical records, vaccinations, dewormings, veterinary treatments, and scheduled follow-ups.</p>
      </div>
      <span class="an-kpi-chip" style="background:#fef3c7;color:#92400e;padding:.35rem .75rem;border-radius:99px;font-size:.78rem;font-weight:700;">
        <i class="bi bi-heart-pulse-fill"></i> <?= count($all_health_records) ?> Medical Records
      </span>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div>
          <h3><i class="bi bi-activity"></i> Clinical Health Log &amp; Treatment History</h3>
          <p>Chronological biosecurity records from farm inspections and veterinary interventions</p>
        </div>
      </div>
      <div class="an-table-wrap">
        <table class="an-table">
          <thead>
            <tr>
              <th>Checkup Date</th>
              <th>Animal Tag</th>
              <th>Farm Facility</th>
              <th>Event Category</th>
              <th>Condition</th>
              <th>Medicine / Treatment</th>
              <th>Administered By</th>
              <th>Next Scheduled</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($all_health_records)): ?>
              <tr><td colspan="9" style="text-align:center;padding:2rem;color:#64748b;">No health records logged yet across registered farms.</td></tr>
            <?php else: ?>
              <?php foreach($all_health_records as $hr): 
                $rType = $hr['record_type'];
                $rClr = $rType === 'Vaccination' ? '#2563eb' : ($rType === 'Deworming' ? '#7c3aed' : ($rType === 'Veterinary Treatment' ? '#d97706' : '#059669'));
                $hc = $hr['health_condition'];
                $hcClr = $hc === 'Healthy' ? '#10b981' : ($hc === 'Sick' ? '#ef4444' : ($hc === 'Critical' ? '#dc2626' : '#f59e0b'));
              ?>
              <tr>
                <td><strong><?= date('M d, Y', strtotime($hr['checkup_date'])) ?></strong></td>
                <td>
                  <strong style="color:#0f172a;"><?= htmlspecialchars($hr['tagId']) ?></strong>
                  <small style="display:block;color:#64748b;"><?= htmlspecialchars($hr['livestock_type']) ?> (<?= htmlspecialchars($hr['breed']?:'Breed') ?>)</small>
                </td>
                <td>
                  <strong><?= htmlspecialchars($hr['farm_name']) ?></strong>
                  <small style="display:block;color:#64748b;"><?= htmlspecialchars($hr['pet_municipality'] ?? 'Camarines Sur') ?></small>
                </td>
                <td>
                  <span style="background:<?= $rClr ?>15;color:<?= $rClr ?>;padding:.15rem .5rem;border-radius:99px;font-size:.72rem;font-weight:700;border:1px solid <?= $rClr ?>40;">
                    <?= htmlspecialchars($rType) ?>
                  </span>
                </td>
                <td>
                  <span style="background:<?= $hcClr ?>15;color:<?= $hcClr ?>;padding:.15rem .5rem;border-radius:99px;font-size:.72rem;font-weight:700;border:1px solid <?= $hcClr ?>40;">
                    <?= htmlspecialchars($hc) ?>
                  </span>
                </td>
                <td>
                  <div><strong><?= htmlspecialchars($hr['medicine_administered'] ?: 'Routine check') ?></strong></div>
                  <?php if(!empty($hr['dosage'])): ?><small style="color:#64748b;">Dosage: <?= htmlspecialchars($hr['dosage']) ?></small><?php endif; ?>
                  <?php if(!empty($hr['treatment_details'])): ?><div style="font-size:.75rem;color:#475569;margin-top:2px;"><?= htmlspecialchars($hr['treatment_details']) ?></div><?php endif; ?>
                </td>
                <td style="font-size:.78rem;color:#334155;"><?= htmlspecialchars($hr['administered_by'] ?: 'Attending Officer') ?></td>
                <td style="font-size:.78rem;color:#059669;font-weight:600;">
                  <?= !empty($hr['next_checkup_date']) ? date('M d, Y', strtotime($hr['next_checkup_date'])) : ' - ' ?>
                </td>
                <td style="text-align:right;">
                  <button type="button" class="btn btn-sm" onclick="deleteHealthRecord(<?= (int)$hr['id'] ?>)" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;padding:.25rem .55rem;border-radius:6px;font-size:.74rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;" title="Delete Health Record">
                    <i class="bi bi-trash-fill"></i> Delete
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ===== 4. VETERINARY & SLAUGHTER ASSISTANCE HUB (VHC & COI) ===== -->
  <?php
    $cntTotalAssist = count($all_assistance_requests);
    $cntVetAssist   = count(array_filter($all_assistance_requests, fn($r) => $r['assistance_type'] === 'Veterinary Assistance'));
    $cntSlaughterAssist = count(array_filter($all_assistance_requests, fn($r) => $r['assistance_type'] === 'Slaughter Assistance'));
    $cntPendingAssist = count(array_filter($all_assistance_requests, fn($r) => in_array($r['status'], ['Request Submitted', 'Pending Review', 'Under Review', 'For Inspection'])));
    $cntCerts = count($all_assistance_certs);
    $cntVhcCerts = count(array_filter($all_assistance_certs, fn($c) => $c['cert_type'] === 'VHC'));
    $cntCoiCerts = count(array_filter($all_assistance_certs, fn($c) => $c['cert_type'] === 'COI'));
  ?>
  <section id="sec-vet-requests" class="section">
    <div class="section-header">
      <div>
        <h3>Assistance Requests &amp; Certificate Processing</h3>
        <p>Unified hub for Farmer Veterinary &amp; Slaughter Assistance. Verify livestock particulars, track inspections, and encode official Veterinary Health Certificates (VHC) and Certificates of Inspection (COI).</p>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <span class="an-kpi-chip" style="background:#ecfdf5;color:#065f46;padding:.35rem .75rem;border-radius:99px;font-size:.78rem;font-weight:700;">
          <i class="bi bi-file-earmark-medical-fill"></i> <?= $cntTotalAssist ?> Requests (<?= $cntPendingAssist ?> Pending)
        </span>
        <span class="an-kpi-chip" style="background:#eff6ff;color:#1e40af;padding:.35rem .75rem;border-radius:99px;font-size:.78rem;font-weight:700;">
          <i class="bi bi-award-fill"></i> <?= $cntCerts ?> Issued Certificates
        </span>
      </div>
    </div>

    <!-- Official Assigned Veterinarian Notification Banner -->
    <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;padding:12px 16px;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:38px;height:38px;border-radius:50%;background:#d1fae5;color:#065f46;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
          <i class="bi bi-patch-check-fill"></i>
        </div>
        <div>
          <strong style="font-size:.88rem;color:#0f172a;display:block;">Assigned LGU Official / Veterinarian: <?= htmlspecialchars($currentLguVet['name'] ?? $currentLguVet['vet_name'] ?? 'Dr. Maria Santos, DVM') ?> (<?= htmlspecialchars($currentLguVet['position'] ?? 'Municipal Veterinarian') ?>)</strong>
          <span style="font-size:.76rem;color:#64748b;">Jurisdiction: <?= htmlspecialchars($currentLguVet['place'] ?? 'Ragay, Camarines Sur') ?> &bull; Automatically prefilled for all VHC and COI certificate processing.</span>
        </div>
      </div>
      <span style="font-size:.74rem;background:#e2e8f0;color:#334155;padding:4px 10px;border-radius:20px;font-weight:700;">
        <i class="bi bi-geo-alt-fill" style="color:#059669;"></i> <?= htmlspecialchars($officerMuni ?: 'Camarines Sur') ?> Official Account
      </span>
    </div>

    <!-- SUB-TABS -->
    <div class="da-subtabs">
      <button type="button" class="da-subtab-btn active" id="daSubtabBtnUnified" onclick="switchDaAssistanceTab('unified')">
        <i class="bi bi-file-earmark-medical-fill"></i> Unified Farmer Requests
        <span class="badge"><?= $cntTotalAssist ?></span>
      </button>
      <button type="button" class="da-subtab-btn" id="daSubtabBtnCerts" onclick="switchDaAssistanceTab('certs')">
        <i class="bi bi-award-fill"></i> Issued Official Certificates (VHC &amp; COI)
        <span class="badge"><?= $cntCerts ?></span>
      </button>
      <button type="button" class="da-subtab-btn" id="daSubtabBtnLegacy" onclick="switchDaAssistanceTab('legacy')">
        <i class="bi bi-inbox-fill"></i> Legacy Clinical Dispatch
        <span class="badge"><?= count($all_vet_requests) ?></span>
      </button>
    </div>

    <!-- TAB 1: UNIFIED FARMER ASSISTANCE REQUESTS -->
    <div id="daAssistanceTabUnified" class="da-assistance-panel">
      <div class="an-card">
        <div class="an-card-head" style="flex-wrap:wrap;gap:12px;">
          <div>
            <h3><i class="bi bi-journal-text"></i> Unified Assistance Request Registry</h3>
            <p>Review farmer requests submitted from the Farmer Portal and encode official certificates</p>
          </div>
          <!-- Filter and Search Box -->
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <select id="daFilterAssistType" class="form-input" onchange="filterDaAssistanceTable()" style="padding:.4rem .7rem;font-size:.82rem;border:1px solid #cbd5e1;border-radius:8px;">
              <option value="ALL">All Types (<?= $cntTotalAssist ?>)</option>
              <option value="Veterinary Assistance">Veterinary Assistance (<?= $cntVetAssist ?>)</option>
              <option value="Slaughter Assistance">Slaughter Assistance (<?= $cntSlaughterAssist ?>)</option>
            </select>
            <select id="daFilterAssistStatus" class="form-input" onchange="filterDaAssistanceTable()" style="padding:.4rem .7rem;font-size:.82rem;border:1px solid #cbd5e1;border-radius:8px;">
              <option value="ALL">All Statuses</option>
              <option value="Pending Review">Pending Review</option>
              <option value="Under Review">Under Review</option>
              <option value="For Inspection">For Inspection</option>
              <option value="Approved">Approved</option>
              <option value="Certificate Processing">Certificate Processing</option>
              <option value="Certificate Issued">Certificate Issued</option>
              <option value="Completed">Completed</option>
              <option value="Rejected">Rejected</option>
            </select>
            <input type="text" id="daSearchAssistInput" placeholder="Search ref, farmer, species..." oninput="filterDaAssistanceTable()" style="padding:.4rem .7rem;font-size:.82rem;border:1px solid #cbd5e1;border-radius:8px;min-width:180px;">
          </div>
        </div>

        <div class="an-table-wrap">
          <table class="an-table" id="daTableUnifiedAssist">
            <thead>
              <tr>
                <th>Reference # &amp; Type</th>
                <th>Farmer / Owner</th>
                <th>Livestock Particulars</th>
                <th>Origin &amp; Destination</th>
                <th>Transport Details</th>
                <th>Status</th>
                <th>Official Certs</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($all_assistance_requests)): ?>
                <tr>
                  <td colspan="8" style="text-align:center;padding:2.5rem;color:#64748b;">
                    <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;color:#94a3b8;"></i>
                    No unified farmer assistance requests submitted yet.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach($all_assistance_requests as $ar): 
                  $isVet = ($ar['assistance_type'] === 'Veterinary Assistance');
                  $typeClr = $isVet ? '#059669' : '#db2777';
                  $typeBg  = $isVet ? '#ecfdf5' : '#fdf2f8';
                  $st = $ar['status'];
                  $stClr = '#475569';
                  $stBg = '#f1f5f9';
                  if (in_array($st, ['Request Submitted', 'Pending Review'])) { $stClr = '#d97706'; $stBg = '#fef3c7'; }
                  elseif (in_array($st, ['Under Review', 'For Inspection'])) { $stClr = '#2563eb'; $stBg = '#dbeafe'; }
                  elseif ($st === 'Approved') { $stClr = '#059669'; $stBg = '#d1fae5'; }
                  elseif ($st === 'Certificate Processing') { $stClr = '#7c3aed'; $stBg = '#ede9fe'; }
                  elseif (in_array($st, ['Certificate Issued', 'Completed'])) { $stClr = '#16a34a'; $stBg = '#dcfce7'; }
                  elseif ($st === 'Rejected') { $stClr = '#dc2626'; $stBg = '#fee2e2'; }
                ?>
                <tr class="da-assist-row" 
                    data-type="<?= htmlspecialchars($ar['assistance_type']) ?>" 
                    data-status="<?= htmlspecialchars($ar['status']) ?>"
                    data-search="<?= htmlspecialchars(strtolower($ar['reference_no'].' '.$ar['farmer_name'].' '.$ar['animal_species'].' '.$ar['origin_barangay'].' '.$ar['origin_municipality'].' '.$ar['dest_municipality'])) ?>">
                  <td>
                    <strong style="font-family:monospace;font-size:.88rem;color:#0f172a;display:block;"><?= htmlspecialchars($ar['reference_no']) ?></strong>
                    <span style="background:<?= $typeBg ?>;color:<?= $typeClr ?>;padding:.15rem .5rem;border-radius:99px;font-size:.7rem;font-weight:700;display:inline-flex;align-items:center;gap:3px;border:1px solid <?= $typeClr ?>30;">
                      <i class="bi <?= $isVet ? 'bi-bandaid-fill' : 'bi-receipt-cutoff' ?>"></i> <?= htmlspecialchars($ar['assistance_type']) ?>
                    </span>
                    <small style="display:block;color:#64748b;font-size:.72rem;margin-top:2px;">
                      <?= date('M d, Y', strtotime($ar['created_at'])) ?>
                    </small>
                  </td>
                  <td>
                    <strong style="color:#0f172a;"><?= htmlspecialchars($ar['farmer_name']) ?></strong>
                    <small style="display:block;color:#64748b;"><i class="bi bi-telephone"></i> <?= htmlspecialchars($ar['farmer_contact'] ?: ' - ') ?></small>
                    <small style="display:block;color:#64748b;"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars(($ar['farmer_barangay'] ? $ar['farmer_barangay'].', ' : '') . ($ar['farmer_municipality'] ?: 'Camarines Sur')) ?></small>
                  </td>
                  <td>
                    <strong style="color:#065f46;"><?= htmlspecialchars($ar['animal_species']) ?> &bull; <?= (int)$ar['head_count'] ?> Heads</strong>
                    <small style="display:block;color:#475569;">
                      Sex: <b><?= htmlspecialchars($ar['sex']) ?></b> <?= !empty($ar['mixed_breakdown']) ? '('.htmlspecialchars($ar['mixed_breakdown']).')' : '' ?>
                    </small>
                    <small style="display:block;color:#64748b;">
                      Age: <?= htmlspecialchars($ar['animal_age'] ?: 'Adult') ?> &bull; Purpose: <b><?= htmlspecialchars($ar['purpose'] ?: 'N/A') ?></b>
                    </small>
                    <?php if (!empty($ar['estimated_value'])): ?>
                      <small style="display:block;color:#047857;font-weight:600;">Est: ₱<?= number_format((float)$ar['estimated_value'], 2) ?></small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="font-size:.78rem;line-height:1.3;">
                      <div><strong style="color:#475569;">Origin:</strong> <?= htmlspecialchars(($ar['origin_barangay'] ? $ar['origin_barangay'].', ' : '').$ar['origin_municipality']) ?></div>
                      <div><strong style="color:#475569;">Dest:</strong> <?= htmlspecialchars($ar['dest_address'] ?: ($ar['dest_municipality'] ? ($ar['dest_barangay'] ? $ar['dest_barangay'].', ' : '').$ar['dest_municipality'] : 'Local / On-Site')) ?></div>
                    </div>
                  </td>
                  <td>
                    <div style="font-size:.78rem;line-height:1.3;">
                      <div><b>Means:</b> <?= htmlspecialchars($ar['transport_means'] ?: 'N/A') ?></div>
                      <div><b>Plate:</b> <code style="font-size:.76rem;font-weight:700;"><?= htmlspecialchars($ar['plate_number'] ?: 'N/A') ?></code></div>
                      <?php if (!empty($ar['proposed_shipment_date'])): ?>
                        <div style="color:#64748b;font-size:.72rem;">Ship Date: <?= htmlspecialchars($ar['proposed_shipment_date']) ?></div>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <span style="background:<?= $stBg ?>;color:<?= $stClr ?>;padding:.2rem .6rem;border-radius:99px;font-size:.72rem;font-weight:800;display:inline-block;white-space:nowrap;border:1px solid <?= $stClr ?>30;">
                      <?= htmlspecialchars($ar['status']) ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex;flex-direction:column;gap:3px;">
                      <?php if ((int)$ar['vhc_count'] > 0): ?>
                        <span style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:2px 6px;border-radius:6px;font-size:.7rem;font-weight:700;white-space:nowrap;display:inline-flex;align-items:center;gap:3px;">
                          <i class="bi bi-shield-check"></i> VHC Issued
                        </span>
                      <?php endif; ?>
                      <?php if ((int)$ar['coi_count'] > 0): ?>
                        <span style="background:#fdf2f8;color:#9d174d;border:1px solid #fbcfe8;padding:2px 6px;border-radius:6px;font-size:.7rem;font-weight:700;white-space:nowrap;display:inline-flex;align-items:center;gap:3px;">
                          <i class="bi bi-receipt"></i> COI Issued
                        </span>
                      <?php endif; ?>
                      <?php if ((int)$ar['vhc_count'] === 0 && (int)$ar['coi_count'] === 0): ?>
                        <span style="color:#94a3b8;font-size:.72rem;font-style:italic;">None yet</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td style="text-align:right;">
                    <div style="display:inline-flex;gap:.35rem;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
                      <button type="button" class="an-btn" style="padding:.28rem .55rem;font-size:.75rem;background:#f8fafc;color:#1e293b;border:1px solid #cbd5e1;" onclick="openDaAssistanceReview(<?= (int)$ar['id'] ?>)" title="Review complete farmer assistance details">
                        <i class="bi bi-eye"></i> Details
                      </button>
                      <button type="button" class="an-btn" style="padding:.28rem .55rem;font-size:.75rem;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;" onclick="openDaAssistanceStatusModal(<?= (int)$ar['id'] ?>, '<?= htmlspecialchars(addslashes($ar['status'])) ?>', '<?= htmlspecialchars(addslashes($ar['official_remarks'] ?? '')) ?>')" title="Update processing status &amp; remarks">
                        <i class="bi bi-arrow-repeat"></i> Status
                      </button>
                      <!-- Encode VHC Certificate button -->
                      <button type="button" class="an-btn" style="padding:.28rem .55rem;font-size:.75rem;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;" onclick="openDaEncodeVhcModal(<?= (int)$ar['id'] ?>)" title="Encode Veterinary Health Certificate (VHC)">
                        <i class="bi bi-shield-plus"></i> VHC
                      </button>
                      <!-- Encode COI Certificate button -->
                      <button type="button" class="an-btn" style="padding:.28rem .55rem;font-size:.75rem;background:#fdf2f8;color:#be185d;border:1px solid #fbcfe8;" onclick="openDaEncodeCoiModal(<?= (int)$ar['id'] ?>)" title="Encode Certificate of Inspection (COI)">
                        <i class="bi bi-receipt-cutoff"></i> COI
                      </button>
                      <!-- Delete button -->
                      <button type="button" class="btn btn-sm" onclick="deleteDaAssistanceRequest(<?= (int)$ar['id'] ?>)" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;padding:.28rem .5rem;border-radius:6px;font-size:.74rem;font-weight:700;cursor:pointer;" title="Delete request">
                        <i class="bi bi-trash-fill"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 2: ISSUED OFFICIAL CERTIFICATES (VHC & COI REGISTRY) -->
    <div id="daAssistanceTabCerts" class="da-assistance-panel" style="display:none;">
      <div class="an-card">
        <div class="an-card-head" style="flex-wrap:wrap;gap:12px;">
          <div>
            <h3><i class="bi bi-award-fill"></i> Official Issued Certificates Registry</h3>
            <p>Archived repository of all prepared Veterinary Health Certificates (VHC) and Certificates of Inspection (COI)</p>
          </div>
          <div style="display:flex;gap:8px;">
            <span class="an-kpi-chip" style="background:#ecfdf5;color:#065f46;padding:.3rem .65rem;border-radius:99px;font-size:.76rem;font-weight:700;">
              VHC: <?= $cntVhcCerts ?>
            </span>
            <span class="an-kpi-chip" style="background:#fdf2f8;color:#9d174d;padding:.3rem .65rem;border-radius:99px;font-size:.76rem;font-weight:700;">
              COI: <?= $cntCoiCerts ?>
            </span>
          </div>
        </div>
        <div class="an-table-wrap">
          <table class="an-table">
            <thead>
              <tr>
                <th>Control Number</th>
                <th>Certificate Type</th>
                <th>Request Ref &amp; Farmer</th>
                <th>Verified Animal Specs</th>
                <th>Origin &amp; Destination</th>
                <th>Assigned LGU Veterinarian</th>
                <th>Date Issued / Expiry</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($all_assistance_certs)): ?>
                <tr>
                  <td colspan="8" style="text-align:center;padding:2.5rem;color:#64748b;">
                    <i class="bi bi-award" style="font-size:2rem;display:block;margin-bottom:8px;color:#94a3b8;"></i>
                    No official certificates encoded yet. Use the <b>VHC</b> or <b>COI</b> button on a request to generate certificates.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach($all_assistance_certs as $ct): 
                  $isVhc = ($ct['cert_type'] === 'VHC');
                  $cClr = $isVhc ? '#059669' : '#be185d';
                  $cBg  = $isVhc ? '#ecfdf5' : '#fdf2f8';
                ?>
                <tr>
                  <td>
                    <strong style="font-family:monospace;font-size:.9rem;color:#0f172a;"><?= htmlspecialchars($ct['control_number']) ?></strong>
                    <small style="display:block;color:#64748b;font-size:.72rem;">Place: <?= htmlspecialchars($ct['place_of_issue']) ?></small>
                  </td>
                  <td>
                    <span style="background:<?= $cBg ?>;color:<?= $cClr ?>;padding:.2rem .6rem;border-radius:99px;font-size:.72rem;font-weight:800;border:1px solid <?= $cClr ?>30;display:inline-flex;align-items:center;gap:4px;">
                      <i class="bi <?= $isVhc ? 'bi-shield-check' : 'bi-receipt-cutoff' ?>"></i> <?= htmlspecialchars($ct['cert_type']) ?>
                    </span>
                  </td>
                  <td>
                    <strong><?= htmlspecialchars($ct['farmer_name']) ?></strong>
                    <small style="display:block;color:#64748b;font-family:monospace;"><?= htmlspecialchars($ct['req_ref']) ?></small>
                  </td>
                  <td>
                    <strong style="color:#0f172a;"><?= htmlspecialchars($ct['verified_species']) ?></strong> &bull; <?= (int)$ct['verified_heads'] ?> Heads
                    <small style="display:block;color:#64748b;">Sex: <?= htmlspecialchars($ct['verified_sex'] ?: 'N/A') ?> &bull; Purpose: <?= htmlspecialchars($ct['purpose'] ?: 'N/A') ?></small>
                  </td>
                  <td>
                    <div style="font-size:.78rem;line-height:1.3;">
                      <div><b style="color:#64748b;">From:</b> <?= htmlspecialchars($ct['origin']) ?></div>
                      <div><b style="color:#64748b;">To:</b> <?= htmlspecialchars($ct['destination']) ?></div>
                    </div>
                  </td>
                  <td>
                    <strong><?= htmlspecialchars($ct['vet_name']) ?></strong>
                    <small style="display:block;color:#64748b;"><?= htmlspecialchars($ct['vet_position'] ?: 'Municipal Veterinarian') ?></small>
                  </td>
                  <td>
                    <div style="font-size:.78rem;">
                      <div><b>Issued:</b> <?= date('M d, Y', strtotime($ct['issue_date'])) ?></div>
                      <?php if (!empty($ct['expiry_date'])): ?>
                        <div style="color:#b45309;"><b>Valid Until:</b> <?= date('M d, Y', strtotime($ct['expiry_date'])) ?></div>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td style="text-align:right;">
                    <button type="button" class="an-btn" style="padding:.3rem .7rem;font-size:.76rem;background:#065f46;color:#fff;border:none;" onclick="viewDaAssistanceCertDoc(<?= (int)$ct['id'] ?>)">
                      <i class="bi bi-printer-fill"></i> View &amp; Print
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 3: LEGACY CLINICAL DISPATCH -->
    <div id="daAssistanceTabLegacy" class="da-assistance-panel" style="display:none;">
      <div class="an-card">
        <div class="an-card-head">
          <div>
            <h3><i class="bi bi-inbox-fill"></i> Clinical Consultations &amp; Emergency Dispatch</h3>
            <p>Review farmer clinical requests and assign attending field technicians or veterinarians</p>
          </div>
        </div>
        <div class="an-table-wrap">
          <table class="an-table">
            <thead>
              <tr>
                <th>Request Code</th>
                <th>Farmer / Contact</th>
                <th>Farm Facility</th>
                <th>Request Category</th>
                <th>Urgency</th>
                <th>Schedule Date</th>
                <th>Status</th>
                <th>Assigned Tech</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($all_vet_requests)): ?>
                <tr><td colspan="9" style="text-align:center;padding:2rem;color:#64748b;">No clinical assistance requests currently pending.</td></tr>
              <?php else: ?>
                <?php foreach($all_vet_requests as $vr): 
                  $uClr = $vr['urgency'] === 'Emergency' ? '#ef4444' : ($vr['urgency'] === 'Urgent' ? '#f59e0b' : '#10b981');
                  $sClr = $vr['status'] === 'Completed' ? '#10b981' : ($vr['status'] === 'In Progress' ? '#3b82f6' : ($vr['status'] === 'Assigned' ? '#8b5cf6' : '#d97706'));
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($vr['request_code']) ?></strong></td>
                  <td>
                    <strong><?= htmlspecialchars(trim(($vr['firstName']??'').' '.($vr['lastName']??''))) ?></strong>
                    <small style="display:block;color:#64748b;"><i class="bi bi-telephone"></i> <?= htmlspecialchars($vr['mobile'] ?: ' - ') ?></small>
                  </td>
                  <td>
                    <strong><?= htmlspecialchars($vr['farm_name']) ?></strong>
                    <small style="display:block;color:#64748b;"><?= htmlspecialchars($vr['pet_municipality'] ?? 'Camarines Sur') ?></small>
                  </td>
                  <td>
                    <strong><?= htmlspecialchars($vr['request_type']) ?></strong>
                    <small style="display:block;color:#64748b;"><?= (int)$vr['animal_count'] ?> <?= htmlspecialchars($vr['livestock_type'] ?: 'Heads') ?></small>
                  </td>
                  <td>
                    <span style="background:<?= $uClr ?>15;color:<?= $uClr ?>;padding:.15rem .5rem;border-radius:99px;font-size:.72rem;font-weight:700;border:1px solid <?= $uClr ?>40;">
                      <?= htmlspecialchars($vr['urgency']) ?>
                    </span>
                  </td>
                  <td style="font-size:.8rem;color:#334155;">
                    <?= !empty($vr['preferred_date']) ? date('M d, Y', strtotime($vr['preferred_date'])) : 'Asap' ?>
                  </td>
                  <td>
                    <span style="background:<?= $sClr ?>15;color:<?= $sClr ?>;padding:.15rem .5rem;border-radius:99px;font-size:.72rem;font-weight:700;border:1px solid <?= $sClr ?>40;">
                      <?= htmlspecialchars($vr['status']) ?>
                    </span>
                  </td>
                  <td style="font-size:.78rem;color:#334155;">
                    <?= !empty($vr['officer_first']) ? htmlspecialchars(trim($vr['officer_first'].' '.$vr['officer_last'])) : '<span style="color:#94a3b8;">Unassigned</span>' ?>
                  </td>
                  <td style="text-align:right;">
                    <div style="display:inline-flex;gap:.35rem;align-items:center;">
                      <button type="button" class="an-btn" style="padding:.3rem .6rem;font-size:.75rem;background:#f8fafc;color:#1e293b;border:1px solid #cbd5e1;" onclick="viewVetRequestDetails(<?= (int)$vr['id'] ?>)" title="View request details of farmer">
                        <i class="bi bi-eye"></i> Details
                      </button>
                      <?php if($vr['status'] === 'Pending'): ?>
                        <button type="button" class="an-btn an-btn-primary" style="padding:.3rem .6rem;font-size:.75rem;" onclick="assignVetRequest(<?= $vr['id'] ?>, 'Assigned')">
                          <i class="bi bi-person-plus"></i> Assign Me
                        </button>
                      <?php elseif($vr['status'] === 'Assigned' || $vr['status'] === 'In Progress'): ?>
                        <button type="button" class="an-btn" style="background:#10b981;color:#fff;border:none;padding:.3rem .6rem;font-size:.75rem;border-radius:6px;" onclick="assignVetRequest(<?= $vr['id'] ?>, 'Completed')">
                          <i class="bi bi-check2-circle"></i> Complete
                        </button>
                      <?php else: ?>
                        <span style="font-size:.74rem;color:#10b981;font-weight:700;">Done</span>
                      <?php endif; ?>
                      <button type="button" class="btn btn-sm" onclick="deleteVetRequest(<?= (int)$vr['id'] ?>)" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;padding:.3rem .55rem;border-radius:6px;font-size:.74rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:3px;" title="Delete Veterinary Request">
                        <i class="bi bi-trash-fill"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== 5. DISEASE SURVEILLANCE ===== -->
  <section id="sec-outbreaks" class="section">
    <div class="section-header">
      <div>
        <h3>Disease Surveillance &amp; Outbreak Containment</h3>
        <p>Monitor suspected animal disease outbreaks, clinical alerts, and activate Bureau of Animal Industry (BAI) 1km/7km containment perimeters.</p>
      </div>
      <span class="an-kpi-chip" style="background:#fef2f2;color:#991b1b;padding:.35rem .75rem;border-radius:99px;font-size:.78rem;font-weight:700;">
        <i class="bi bi-shield-shaded"></i> <?= count($disease_surveillance) ?> Critical Alerts
      </span>
    </div>

    <!-- BAI Containment Protocols Banner -->
    <div style="background:#fff;border:1px solid #fecaca;border-radius:14px;padding:1.15rem;margin-bottom:1.25rem;">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem;">
        <div style="font-size:1.8rem;color:#ef4444;"><i class="bi bi-exclamation-octagon-fill"></i></div>
        <div>
          <strong style="font-size:1rem;color:#991b1b;display:block;">Bureau of Animal Industry (BAI) Active Containment Protocols</strong>
          <span style="font-size:.8rem;color:#475569;">When an outbreak (ASF, FMD, Avian Influenza) is suspected, enforce strict movement restrictions:</span>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.75rem;margin-top:.75rem;font-size:.78rem;">
        <div style="background:#fef2f2;padding:.75rem;border-radius:8px;border-left:4px solid #ef4444;">
          <strong style="color:#991b1b;display:block;"><i class="bi bi-circle-fill" style="color:#ef4444;font-size:0.7rem;"></i> 1km Quarantine Ring</strong>
          <span>Complete ban on live animal transit, mandatory culling and disinfection within 1000m.</span>
        </div>
        <div style="background:#fffbeb;padding:.75rem;border-radius:8px;border-left:4px solid #f59e0b;">
          <strong style="color:#b45309;display:block;"><i class="bi bi-circle-fill" style="color:#f97316;font-size:0.7rem;"></i> 7km Surveillance Buffer</strong>
          <span>Mandatory blood testing, clinical monitoring, and checkpoint inspection within 7000m.</span>
        </div>
      </div>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div>
          <h3><i class="bi bi-radar"></i> Active Outbreak &amp; Critical Health Incidents</h3>
          <p>Prioritized suspected disease cases requiring immediate clinical verification</p>
        </div>
      </div>
      <div class="an-table-wrap">
        <table class="an-table">
          <thead>
            <tr>
              <th>Date Reported</th>
              <th>Incident Title</th>
              <th>Farm / Municipality</th>
              <th>Category</th>
              <th>Priority</th>
              <th>Symptoms</th>
              <th>Status</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($disease_surveillance)): ?>
              <tr><td colspan="8" style="text-align:center;padding:2rem;color:#64748b;">No active disease outbreaks reported in District I.</td></tr>
            <?php else: ?>
              <?php foreach($disease_surveillance as $ds): 
                $pClr = $ds['priority'] === 'Critical' ? '#ef4444' : ($ds['priority'] === 'High' ? '#f59e0b' : '#3b82f6');
              ?>
              <tr>
                <td><?= date('M d, Y', strtotime($ds['createdAt'])) ?></td>
                <td><strong style="color:#0f172a;"><?= htmlspecialchars($ds['title']) ?></strong></td>
                <td>
                  <strong><?= htmlspecialchars($ds['farm_name'] ?: 'General Area') ?></strong>
                  <small style="display:block;color:#64748b;"><?= htmlspecialchars($ds['pet_municipality'] ?? 'Camarines Sur') ?></small>
                </td>
                <td><span style="font-weight:700;color:#991b1b;"><?= htmlspecialchars($ds['type']) ?></span></td>
                <td>
                  <span style="background:<?= $pClr ?>15;color:<?= $pClr ?>;padding:.15rem .5rem;border-radius:99px;font-size:.72rem;font-weight:700;border:1px solid <?= $pClr ?>40;">
                    <?= htmlspecialchars($ds['priority']) ?>
                  </span>
                </td>
                <td style="font-size:.78rem;color:#475569;max-width:240px;"><?= htmlspecialchars($ds['symptoms'] ?: $ds['description']) ?></td>
                <td>
                  <span style="background:#f1f5f9;color:#334155;padding:.15rem .5rem;border-radius:99px;font-size:.72rem;font-weight:700;">
                    <?= htmlspecialchars($ds['status']) ?>
                  </span>
                </td>
                <td style="text-align:right;">
                  <button type="button" class="an-btn an-btn-primary" style="padding:.3rem .6rem;font-size:.75rem;" onclick="activateSection('map')">
                    <i class="bi bi-geo-alt"></i> View on Map
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ===== 6. SLAUGHTER MONITORING ===== -->
  <?php 
    $unified_slaughter_requests = array_filter($all_assistance_requests, fn($r) => $r['assistance_type'] === 'Slaughter Assistance');
  ?>
  <section id="sec-slaughter" class="section">
    <div class="section-header">
      <div>
        <h3>Slaughter Monitoring &amp; Meat Safety</h3>
        <p>Review declared slaughter activities across household, municipal abattoir, emergency culling, and unauthorized reports.</p>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <span class="an-kpi-chip" style="background:#fdf2f8;color:#9d174d;padding:.35rem .75rem;border-radius:99px;font-size:.78rem;font-weight:700;">
          <i class="bi bi-file-earmark-diff-fill"></i> <?= count($unified_slaughter_requests) ?> Portal Slaughter Requests
        </span>
        <span class="an-kpi-chip" style="background:#fdf2f8;color:#9d174d;padding:.35rem .75rem;border-radius:99px;font-size:.78rem;font-weight:700;">
          <i class="bi bi-receipt-cutoff"></i> <?= count($all_slaughter) ?> Declarations
        </span>
      </div>
    </div>

    <!-- Unified Slaughter Assistance & COI Quick Processing Card -->
    <div class="an-card" style="margin-bottom:1.5rem;border-left:4px solid #db2777;">
      <div class="an-card-head" style="background:#fff1f2;flex-wrap:wrap;gap:10px;">
        <div>
          <h3 style="color:#9d174d;"><i class="bi bi-truck-front-fill"></i> Unified Portal Slaughter Assistance &amp; COI Requests</h3>
          <p style="color:#831843;">Direct farmer requests for slaughter transport permits and Certificate of Inspection (COI) issuance</p>
        </div>
        <button type="button" class="an-btn" style="background:#db2777;color:#fff;font-size:.78rem;font-weight:700;" onclick="navTo('vet-requests');setTimeout(()=>switchDaAssistanceTab('unified'),150)">
          <i class="bi bi-arrow-right"></i> Open Assistance Hub
        </button>
      </div>
      <div class="an-table-wrap">
        <table class="an-table">
          <thead>
            <tr>
              <th>Request Ref</th>
              <th>Farmer / Shipper</th>
              <th>Livestock &amp; Value</th>
              <th>Origin &rarr; Destination</th>
              <th>Transport &amp; Plate</th>
              <th>Status</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($unified_slaughter_requests)): ?>
              <tr>
                <td colspan="7" style="text-align:center;padding:1.5rem;color:#64748b;">
                  No pending slaughter assistance requests from the Unified Farmer Portal at this time.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach($unified_slaughter_requests as $usr): 
                $st = $usr['status'];
                $stClr = in_array($st, ['Request Submitted', 'Pending Review']) ? '#d97706' : ($st === 'Certificate Issued' ? '#16a34a' : '#2563eb');
                $stBg  = in_array($st, ['Request Submitted', 'Pending Review']) ? '#fef3c7' : ($st === 'Certificate Issued' ? '#dcfce7' : '#dbeafe');
              ?>
              <tr>
                <td>
                  <strong style="font-family:monospace;font-size:.86rem;color:#0f172a;"><?= htmlspecialchars($usr['reference_no']) ?></strong>
                  <small style="display:block;color:#64748b;font-size:.72rem;"><?= date('M d, Y', strtotime($usr['created_at'])) ?></small>
                </td>
                <td>
                  <strong style="color:#0f172a;"><?= htmlspecialchars($usr['farmer_name']) ?></strong>
                  <small style="display:block;color:#64748b;"><i class="bi bi-telephone"></i> <?= htmlspecialchars($usr['farmer_contact'] ?: ' - ') ?></small>
                </td>
                <td>
                  <strong style="color:#065f46;"><?= htmlspecialchars($usr['animal_species']) ?> &bull; <?= (int)$usr['head_count'] ?> Heads</strong>
                  <small style="display:block;color:#475569;">Purpose: <?= htmlspecialchars($usr['purpose'] ?: 'Slaughter') ?></small>
                  <?php if (!empty($usr['estimated_value'])): ?>
                    <small style="display:block;color:#047857;font-weight:600;">Val: ₱<?= number_format((float)$usr['estimated_value'], 2) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="font-size:.78rem;line-height:1.3;">
                    <div><b>From:</b> <?= htmlspecialchars(($usr['origin_barangay'] ? $usr['origin_barangay'].', ' : '').$usr['origin_municipality']) ?></div>
                    <div><b>To:</b> <?= htmlspecialchars($usr['dest_address'] ?: ($usr['dest_municipality'] ?: 'Abattoir')) ?></div>
                  </div>
                </td>
                <td>
                  <div style="font-size:.78rem;line-height:1.3;">
                    <div><?= htmlspecialchars($usr['transport_means'] ?: 'Forward Truck') ?></div>
                    <div><code><?= htmlspecialchars($usr['plate_number'] ?: 'N/A') ?></code></div>
                  </div>
                </td>
                <td>
                  <span style="background:<?= $stBg ?>;color:<?= $stClr ?>;padding:.15rem .5rem;border-radius:99px;font-size:.72rem;font-weight:800;border:1px solid <?= $stClr ?>30;">
                    <?= htmlspecialchars($usr['status']) ?>
                  </span>
                </td>
                <td style="text-align:right;">
                  <div style="display:inline-flex;gap:.35rem;align-items:center;">
                    <button type="button" class="an-btn" style="padding:.28rem .55rem;font-size:.74rem;background:#f8fafc;color:#1e293b;border:1px solid #cbd5e1;" onclick="openDaAssistanceReview(<?= (int)$usr['id'] ?>)">
                      <i class="bi bi-eye"></i> Details
                    </button>
                    <button type="button" class="an-btn" style="padding:.28rem .55rem;font-size:.74rem;background:#fdf2f8;color:#be185d;border:1px solid #fbcfe8;" onclick="openDaEncodeCoiModal(<?= (int)$usr['id'] ?>)">
                      <i class="bi bi-receipt-cutoff"></i> Issue COI
                    </button>
                    <button type="button" class="an-btn" style="padding:.28rem .55rem;font-size:.74rem;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;" onclick="openDaEncodeVhcModal(<?= (int)$usr['id'] ?>)">
                      <i class="bi bi-shield-plus"></i> Issue VHC
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div>
          <h3><i class="bi bi-clipboard-check"></i> Slaughter Declaration Registry</h3>
          <p>Inspect Ante-Mortem certifications, slaughter locations, and regulatory compliance</p>
        </div>
      </div>
      <div class="an-table-wrap">
        <table class="an-table">
          <thead>
            <tr>
              <th>Declaration No</th>
              <th>Farmer Name</th>
              <th>Animal Tag &amp; Species</th>
              <th>Slaughter Category</th>
              <th>Date &amp; Location</th>
              <th>Purpose</th>
              <th>Status</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($all_slaughter)): ?>
              <tr><td colspan="8" style="text-align:center;padding:2rem;color:#64748b;">No slaughter declarations recorded yet.</td></tr>
            <?php else: ?>
              <?php foreach($all_slaughter as $sl): 
                $sClr = $sl['status'] === 'Approved' || $sl['status'] === 'Verified' ? '#10b981' : ($sl['status'] === 'Flagged/Investigating' ? '#ef4444' : '#d97706');
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($sl['declaration_no']) ?></strong></td>
                <td>
                  <strong><?= htmlspecialchars(trim(($sl['firstName']??'').' '.($sl['lastName']??''))) ?></strong>
                  <small style="display:block;color:#64748b;"><?= htmlspecialchars($sl['farm_name']) ?></small>
                </td>
                <td>
                  <strong><?= htmlspecialchars($sl['animal_tag_id']) ?></strong>
                  <small style="display:block;color:#64748b;"><?= htmlspecialchars($sl['livestock_type']) ?></small>
                </td>
                <td style="font-size:.8rem;color:#0f172a;font-weight:600;"><?= htmlspecialchars($sl['slaughter_type']) ?></td>
                <td>
                  <div><?= date('M d, Y', strtotime($sl['slaughter_date'])) ?></div>
                  <small style="color:#64748b;"><?= htmlspecialchars($sl['location']) ?></small>
                </td>
                <td style="font-size:.78rem;color:#475569;max-width:200px;"><?= htmlspecialchars($sl['purpose']) ?></td>
                <td>
                  <span style="background:<?= $sClr ?>15;color:<?= $sClr ?>;padding:.15rem .5rem;border-radius:99px;font-size:.72rem;font-weight:700;border:1px solid <?= $sClr ?>40;">
                    <?= htmlspecialchars($sl['status']) ?>
                  </span>
                </td>
                <td style="text-align:right;">
                  <div style="display:inline-flex;gap:.35rem;align-items:center;">
                    <button type="button" class="an-btn" style="padding:.3rem .6rem;font-size:.75rem;background:#f8fafc;color:#1e293b;border:1px solid #cbd5e1;" onclick="viewSlaughterDetails(<?= (int)$sl['id'] ?>)" title="View all declaration details">
                      <i class="bi bi-eye"></i> Details
                    </button>
                    <?php if($sl['status'] === 'Pending Review'): ?>
                      <button type="button" class="an-btn an-btn-primary" style="padding:.3rem .6rem;font-size:.75rem;" onclick="setSlaughterStatus(<?= $sl['id'] ?>, 'Verified')">
                        <i class="bi bi-check-lg"></i> Verify
                      </button>
                    <?php else: ?>
                      <span style="font-size:.74rem;color:#10b981;font-weight:700;">Verified</span>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm" onclick="deleteSlaughterDeclaration(<?= (int)$sl['id'] ?>)" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;padding:.3rem .55rem;border-radius:6px;font-size:.74rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:3px;" title="Delete Declaration">
                      <i class="bi bi-trash-fill"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ===== 7. INSURANCE ASSISTANCE ===== -->
  <section id="sec-insurance" class="section">
    <div class="section-header">
      <div>
        <h3>Livestock Insurance Assistance</h3>
        <p>Review Philippine Crop Insurance Corporation (PCIC) referrals, policy enrollments, and mortality claim endorsements.</p>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <button type="button" class="an-btn an-btn-primary" style="font-size:.78rem;padding:.4rem .85rem;" onclick="openDaIndemnityModal(null)">
          <i class="bi bi-file-earmark-check-fill"></i> Issue Indemnity Claims Certificate
        </button>
        <span class="an-kpi-chip" style="background:#fef3c7;color:#b45309;padding:.35rem .75rem;border-radius:99px;font-size:.78rem;font-weight:700;">
          <i class="bi bi-shield-check"></i> <?= count($all_insurance) ?> Referrals
        </span>
      </div>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div>
          <h3><i class="bi bi-file-earmark-medical-fill"></i> PCIC Application &amp; Mortality Claims Registry</h3>
          <p>Endorse qualified raisers to PCIC regional branch for free insurance protection</p>
        </div>
      </div>
      <div class="an-table-wrap">
        <table class="an-table">
          <thead>
            <tr>
              <th>Reference No</th>
              <th>Farmer Name</th>
              <th>Farm Facility</th>
              <th>Request Type</th>
              <th>Species &amp; Heads</th>
              <th>Loss Amount</th>
              <th>PCIC Status</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($all_insurance)): ?>
              <tr><td colspan="8" style="text-align:center;padding:2rem;color:#64748b;">No insurance referrals or claims recorded yet.</td></tr>
            <?php else: ?>
              <?php foreach($all_insurance as $in): 
                $iClr = match($in['pcic_status']) {
                  'Claim Approved', 'Disbursed' => '#10b981',
                  'Endorsed to PCIC', 'Under PCIC Review' => '#3b82f6',
                  'Rejected' => '#ef4444',
                  default => '#f59e0b'
                };
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($in['reference_no']) ?></strong></td>
                <td>
                  <strong><?= htmlspecialchars(trim(($in['firstName']??'').' '.($in['lastName']??''))) ?></strong>
                  <small style="display:block;color:#64748b;"><?= htmlspecialchars($in['mobile'] ?: ' - ') ?></small>
                </td>
                <td>
                  <strong><?= htmlspecialchars($in['farm_name']) ?></strong>
                  <small style="display:block;color:#64748b;"><?= htmlspecialchars($in['pet_municipality'] ?? 'Camarines Sur') ?></small>
                </td>
                <td style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($in['request_type']) ?></td>
                <td><strong><?= (int)$in['head_count'] ?></strong> <?= htmlspecialchars($in['livestock_type']) ?></td>
                <td style="font-weight:700;color:#059669;">
                  <?= (float)$in['estimated_loss'] > 0 ? ('PHP ' . number_format((float)$in['estimated_loss'], 2)) : ' - ' ?>
                </td>
                <td>
                  <span style="background:<?= $iClr ?>15;color:<?= $iClr ?>;padding:.15rem .5rem;border-radius:99px;font-size:.72rem;font-weight:700;border:1px solid <?= $iClr ?>40;">
                    <?= htmlspecialchars($in['pcic_status']) ?>
                  </span>
                </td>
                <td style="text-align:right;">
                  <div style="display:inline-flex;gap:.35rem;">
                    <?php if($in['pcic_status'] === 'Pending Verification'): ?>
                      <button type="button" class="an-btn an-btn-primary" style="padding:.3rem .6rem;font-size:.75rem;" onclick="setInsuranceStatus(<?= $in['id'] ?>, 'Endorsed to PCIC')">
                        <i class="bi bi-send-check"></i> Endorse
                      </button>
                    <?php elseif($in['pcic_status'] === 'Endorsed to PCIC'): ?>
                      <button type="button" class="an-btn" style="background:#10b981;color:#fff;border:none;padding:.3rem .6rem;font-size:.75rem;border-radius:6px;" onclick="setInsuranceStatus(<?= $in['id'] ?>, 'Claim Approved')">
                        <i class="bi bi-check-circle"></i> Approve
                      </button>
                    <?php else: ?>
                      <span style="font-size:.74rem;color:#10b981;font-weight:700;">Approved</span>
                    <?php endif; ?>
                    <button type="button" class="an-btn" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;padding:.3rem .6rem;font-size:.74rem;" onclick='openDaIndemnityModal(<?= htmlspecialchars(json_encode([
                      "id" => (int)$in["id"],
                      "farmer_id" => (int)($in["farmer_id"] ?? 0),
                      "farmer_name" => trim(($in["firstName"]??"")." ".($in["lastName"]??"")),
                      "farmer_barangay" => $in["pet_barangay"] ?? "",
                      "municipality" => $in["pet_municipality"] ?? $officerMuni,
                      "livestock_type" => $in["livestock_type"] ?? "",
                      "estimated_loss" => (float)($in["estimated_loss"] ?? 0),
                      "reference_no" => $in["reference_no"] ?? ""
                    ]), ENT_QUOTES, "UTF-8") ?>)'>
                      <i class="bi bi-award-fill"></i> Indemnity Cert
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Issued Indemnity Claims Certificates Registry -->
    <div class="an-card" style="margin-top:1.25rem;">
      <div class="an-card-head">
        <div>
          <h3><i class="bi bi-patch-check-fill" style="color:#4338ca;"></i> Issued Indemnity Claim Certificates</h3>
          <p>Verified insurance indemnity claims with complete Barangay Certification, 2 Unrelated Witnesses, Owner Affidavit &amp; Mortality Photos</p>
        </div>
        <span class="an-kpi-chip" style="background:#eef2ff;color:#4338ca;padding:.3rem .7rem;border-radius:99px;font-size:.76rem;font-weight:700;">
          <?= count($all_indemnity_certs) ?> Issued
        </span>
      </div>
      <div class="an-table-wrap">
        <table class="an-table">
          <thead>
            <tr>
              <th>Control No.</th>
              <th>Farmer / Barangay</th>
              <th>Animal &amp; Gender</th>
              <th>Date of Loss &amp; Cause</th>
              <th>Witnesses Verified</th>
              <th>Date Issued</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($all_indemnity_certs)): ?>
              <tr><td colspan="7" style="text-align:center;padding:1.75rem;color:#64748b;">No Indemnity Claim Certificates issued yet.</td></tr>
            <?php else: ?>
              <?php foreach ($all_indemnity_certs as $ic): ?>
              <tr>
                <td><code style="font-weight:800;color:#4338ca;"><?= htmlspecialchars($ic['control_number']) ?></code></td>
                <td>
                  <strong><?= htmlspecialchars($ic['farmer_name']) ?></strong>
                  <small style="display:block;color:#64748b;"><?= htmlspecialchars(($ic['farmer_barangay'] ? 'Brgy. ' . $ic['farmer_barangay'] . ', ' : '') . $ic['municipality']) ?></small>
                </td>
                <td><strong><?= htmlspecialchars($ic['animal_kind_gender']) ?></strong></td>
                <td>
                  <div style="font-size:.8rem;"><b><?= date('M d, Y', strtotime($ic['date_of_loss'])) ?></b></div>
                  <small style="color:#64748b;"><?= htmlspecialchars($ic['cause_of_death']) ?></small>
                </td>
                <td style="font-size:.78rem;">
                  <div>1. <?= htmlspecialchars($ic['witness1_name'] ?: '-') ?></div>
                  <div>2. <?= htmlspecialchars($ic['witness2_name'] ?: '-') ?></div>
                </td>
                <td style="font-size:.8rem;"><?= date('M d, Y', strtotime($ic['issue_date'])) ?></td>
                <td style="text-align:right;">
                  <button type="button" class="an-btn" style="background:#4338ca;color:#fff;border:none;padding:.3rem .65rem;font-size:.75rem;" onclick="viewDaIndemnityCertDoc(<?= (int)$ic['id'] ?>)">
                    <i class="bi bi-printer-fill"></i> View &amp; Print
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ===== 8. DOCUMENT VERIFICATION ===== -->
  <section id="sec-documents" class="section">
    <div class="section-header">
      <div>
        <h3>Document Verification</h3>
        <p>Authenticate and inspect submitted certificates: ownership titles, veterinary health certificates, vaccination records, and shipping permits.</p>
      </div>
      <span class="an-kpi-chip" style="background:#ecfdf5;color:#065f46;padding:.35rem .75rem;border-radius:99px;font-size:.78rem;font-weight:700;">
        <i class="bi bi-patch-check-fill"></i> <?= count($all_certificates) ?> Verified Documents
      </span>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div>
          <h3><i class="bi bi-file-earmark-lock-fill"></i> Official Certificate Clearance Desk</h3>
          <p>Review validity dates, issuing authorities, and attached clearance documents</p>
        </div>
      </div>
      <div class="an-table-wrap">
        <table class="an-table">
          <thead>
            <tr>
              <th>Certificate No</th>
              <th>Document Title / Type</th>
              <th>Farm Facility</th>
              <th>Raiser Name</th>
              <th>Issuing Authority</th>
              <th>Valid Until</th>
              <th>Status</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($all_certificates)): ?>
              <tr><td colspan="8" style="text-align:center;padding:2rem;color:#64748b;">No certificates submitted for verification yet.</td></tr>
            <?php else: ?>
              <?php foreach($all_certificates as $ct): 
                $isExp = !empty($ct['expiry_date']) && strtotime($ct['expiry_date']) < time();
                $cClr = $isExp ? '#ef4444' : '#10b981';
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($ct['cert_number']) ?></strong></td>
                <td>
                  <strong><?= htmlspecialchars($ct['document_title'] ?: $ct['cert_type']) ?></strong>
                  <small style="display:block;color:#64748b;"><?= htmlspecialchars($ct['cert_type']) ?></small>
                </td>
                <td>
                  <strong><?= htmlspecialchars($ct['farm_name'] ?: 'General Farm') ?></strong>
                  <small style="display:block;color:#64748b;"><?= htmlspecialchars($ct['pet_municipality'] ?? 'Camarines Sur') ?></small>
                </td>
                <td><?= htmlspecialchars(trim(($ct['firstName']??'').' '.($ct['lastName']??''))) ?></td>
                <td style="font-size:.78rem;color:#475569;"><?= htmlspecialchars($ct['issued_by'] ?? 'DA-RFO V') ?></td>
                <td style="font-size:.78rem;color:#334155;">
                  <?= !empty($ct['expiry_date']) ? date('M d, Y', strtotime($ct['expiry_date'])) : 'Indefinite' ?>
                </td>
                <td>
                  <span style="background:<?= $cClr ?>15;color:<?= $cClr ?>;padding:.15rem .5rem;border-radius:99px;font-size:.72rem;font-weight:700;border:1px solid <?= $cClr ?>40;">
                    <?= $isExp ? 'EXPIRED' : htmlspecialchars($ct['status'] ?: 'Active') ?>
                  </span>
                </td>
                <td style="text-align:right;">
                  <div style="display:inline-flex;gap:.35rem;">
                    <?php if(!empty($ct['file_url'])): ?>
                      <a href="<?= htmlspecialchars(app_url($ct['file_url'])) ?>" target="_blank" class="an-btn an-btn-primary" style="padding:.28rem .6rem;font-size:.74rem;text-decoration:none;">
                        <i class="bi bi-file-earmark-arrow-down"></i> View
                      </a>
                    <?php else: ?>
                      <span style="font-size:.72rem;color:#94a3b8;">Auto Cert</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>


  <!-- ===== FORM SUBMISSIONS REVIEW (FARM REG, RSBSA, PCIC) ===== -->
  <section id="sec-form-review" class="section">
    <div class="page-heading">
      <h1>Form Submissions &amp; Verification Review</h1>
      <p>Review, view exact 8.5" &times; 13" Folio forms, download official PDFs, and accept or decline farmer applications with remarks.</p>
    </div>

    <!-- TABS -->
    <div class="da-review-tabs">
      <button type="button" class="da-review-tab active" id="tabBtn-freg" onclick="switchReviewTab('freg')">
        <i class="bi bi-house-door-fill"></i> Farm Registrations <span class="tab-badge"><?= count($farmRegSubmissions) ?></span>
      </button>
      <button type="button" class="da-review-tab" id="tabBtn-rsbsa" onclick="switchReviewTab('rsbsa')">
        <i class="bi bi-file-earmark-text-fill"></i> RSBSA Enrollments <span class="tab-badge"><?= count($rsbsaSubmissions) ?></span>
      </button>
      <button type="button" class="da-review-tab" id="tabBtn-pcic" onclick="switchReviewTab('pcic')">
        <i class="bi bi-shield-check"></i> PCIC Insurance Applications <span class="tab-badge"><?= count($pcicSubmissions) ?></span>
      </button>
    </div>

    <!-- FILTER BAR -->
    <div class="da-review-filter-bar" style="background:#fff;padding:1rem 1.25rem;border-radius:12px;margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;box-shadow:0 1px 3px rgba(0,0,0,.05);">
      <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:220px;">
        <i class="bi bi-search" style="color:#94a3b8;"></i>
        <input type="text" id="reviewSearchInput" oninput="filterReviewSubmissions()" placeholder="Search by farmer name, farm, reference / application number..." style="width:100%;border:none;outline:none;font-size:.875rem;">
      </div>
      <div style="display:flex;gap:10px;align-items:center;">
        <label style="font-size:.8rem;font-weight:600;color:#64748b;">Status:</label>
        <select id="reviewStatusFilter" onchange="filterReviewSubmissions()" style="padding:.4rem .75rem;border-radius:8px;border:1px solid #cbd5e1;font-size:.8rem;">
          <option value="all">All Statuses</option>
          <option value="pending">Pending Only</option>
          <option value="accepted">Accepted</option>
          <option value="declined">Declined</option>
        </select>
      </div>
    </div>

    <!-- TAB PANE 1: FARM REGISTRATIONS -->
    <div id="pane-freg" class="review-pane">
      <div class="card" style="padding:0;overflow:hidden;">
        <div style="overflow-x:auto;">
          <table class="data-table" id="tbl-freg">
            <thead>
              <tr>
                <th style="width:60px;">ID</th>
                <th>Farmer Name</th>
                <th>Farm Name</th>
                <th>Address</th>
                <th>Submitted Date</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($farmRegSubmissions)): ?>
                <tr><td colspan="7" class="empty-state"><i class="bi bi-inbox"></i><h4>No Farm Registration submissions yet</h4></td></tr>
              <?php else: foreach($farmRegSubmissions as $row): 
                $stClass = strtolower($row['status']);
                $fNameClean = htmlspecialchars($row['farmer_name'] ?: 'Farmer');
              ?>
                <tr class="review-row" data-search="<?= strtolower(htmlspecialchars(($row['farmer_name']??'').' '.($row['farm_name']??'').' '.($row['farm_address']??''))) ?>" data-status="<?= $stClass ?>">
                  <td style="font-weight:700;">#<?= $row['id'] ?></td>
                  <td><b><?= $fNameClean ?></b><div style="font-size:11px;color:#64748b;"><?= htmlspecialchars(($row['farmer_mobile']?:$row['farmer_email'])?:'') ?></div></td>
                  <td><?= htmlspecialchars($row['farm_name'] ?? 'N/A') ?></td>
                  <td style="font-size:12px;"><?= htmlspecialchars($row['farm_address'] ?? 'N/A') ?></td>
                  <td style="font-size:12px;white-space:nowrap;"><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                  <td>
                    <span class="status-pill status-<?= $stClass ?>"><?= ucfirst($stClass) ?></span>
                    <?php if(!empty($row['remarks']) && $stClass==='declined'): ?>
                      <div style="font-size:10px;color:#dc2626;max-width:180px;margin-top:2px;" title="<?= htmlspecialchars($row['remarks']) ?>"><b>Reason:</b> <?= htmlspecialchars(substr($row['remarks'], 0, 40)) ?>...</div>
                    <?php endif; ?>
                  </td>
                  <td style="text-align:right;white-space:nowrap;">
                    <button type="button" class="btn btn-sm btn-secondary btn-pdf-download" onclick="downloadSubmissionPdf('farm_registration', <?= $row['id'] ?>, '<?= addslashes($fNameClean) ?>', '<?= date('Ymd', strtotime($row['created_at'])) ?>')" title="Download PDF of Farm Registration Form"><i class="bi bi-file-earmark-pdf-fill"></i> Download PDF</button>
                    <?php if($stClass !== 'accepted'): ?>
                      <button type="button" class="btn btn-sm btn-success" onclick="acceptSubmission('farm_registration', <?= $row['id'] ?>, '<?= addslashes($fNameClean) ?>')" style="background:#10b981;color:#fff;border:none;"><i class="bi bi-check-lg"></i> Accept</button>
                    <?php endif; ?>
                    <?php if($stClass !== 'declined'): ?>
                      <button type="button" class="btn btn-sm btn-danger" onclick="openDeclineModal('farm_registration', <?= $row['id'] ?>, '<?= addslashes($fNameClean) ?>')" style="background:#ef4444;color:#fff;border:none;"><i class="bi bi-x-lg"></i> Decline</button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB PANE 2: RSBSA ENROLLMENTS -->
    <div id="pane-rsbsa" class="review-pane" style="display:none;">
      <div class="card" style="padding:0;overflow:hidden;">
        <div style="overflow-x:auto;">
          <table class="data-table" id="tbl-rsbsa">
            <thead>
              <tr>
                <th style="width:60px;">ID</th>
                <th>Reference No</th>
                <th>Farmer Name</th>
                <th>Barangay / Municipality</th>
                <th>Livelihood</th>
                <th>Submitted Date</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($rsbsaSubmissions)): ?>
                <tr><td colspan="8" class="empty-state"><i class="bi bi-inbox"></i><h4>No RSBSA Enrollment submissions yet</h4></td></tr>
              <?php else: foreach($rsbsaSubmissions as $row): 
                $stClass = strtolower($row['status']);
                $fNameClean = htmlspecialchars($row['farmer_name'] ?: ($row['applicant_name'] ?: 'Farmer'));
              ?>
                <tr class="review-row" data-search="<?= strtolower(htmlspecialchars(($row['reference_no']??'').' '.$fNameClean.' '.($row['barangay']??'').' '.($row['municipality']??''))) ?>" data-status="<?= $stClass ?>">
                  <td style="font-weight:700;">#<?= $row['id'] ?></td>
                  <td><code style="font-weight:700;background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($row['reference_no'] ?? 'N/A') ?></code></td>
                  <td><b><?= $fNameClean ?></b></td>
                  <td style="font-size:12px;"><?= htmlspecialchars(($row['barangay'] ?: 'Poblacion').', '.($row['municipality'] ?: 'Camarines Sur')) ?></td>
                  <td><span class="badge" style="background:#e0f2fe;color:#0369a1;font-size:11px;"><?= htmlspecialchars($row['livelihood_category'] ?? 'Farmer') ?></span></td>
                  <td style="font-size:12px;white-space:nowrap;"><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                  <td>
                    <span class="status-pill status-<?= $stClass ?>"><?= ucfirst($stClass) ?></span>
                    <?php if(!empty($row['remarks']) && $stClass==='declined'): ?>
                      <div style="font-size:10px;color:#dc2626;max-width:180px;margin-top:2px;" title="<?= htmlspecialchars($row['remarks']) ?>"><b>Reason:</b> <?= htmlspecialchars(substr($row['remarks'], 0, 40)) ?>...</div>
                    <?php endif; ?>
                  </td>
                  <td style="text-align:right;white-space:nowrap;">
                    <button type="button" class="btn btn-sm btn-secondary btn-pdf-download" onclick="downloadSubmissionPdf('rsbsa', <?= $row['id'] ?>, '<?= addslashes($fNameClean) ?>', '<?= date('Ymd', strtotime($row['created_at'])) ?>')" title="Download PDF of DA-RSBSA Enrollment Form"><i class="bi bi-file-earmark-pdf-fill"></i> Download PDF</button>
                    <?php if($stClass !== 'accepted'): ?>
                      <button type="button" class="btn btn-sm btn-success" onclick="acceptSubmission('rsbsa', <?= $row['id'] ?>, '<?= addslashes($fNameClean) ?>')" style="background:#10b981;color:#fff;border:none;"><i class="bi bi-check-lg"></i> Accept</button>
                    <?php endif; ?>
                    <?php if($stClass !== 'declined'): ?>
                      <button type="button" class="btn btn-sm btn-danger" onclick="openDeclineModal('rsbsa', <?= $row['id'] ?>, '<?= addslashes($fNameClean) ?>')" style="background:#ef4444;color:#fff;border:none;"><i class="bi bi-x-lg"></i> Decline</button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB PANE 3: PCIC LIVESTOCK INSURANCE -->
    <div id="pane-pcic" class="review-pane" style="display:none;">
      <div class="card" style="padding:0;overflow:hidden;">
        <div style="overflow-x:auto;">
          <table class="data-table" id="tbl-pcic">
            <thead>
              <tr>
                <th style="width:60px;">ID</th>
                <th>App Number</th>
                <th>Farmer Name</th>
                <th>Farm Location</th>
                <th>Animal Type</th>
                <th>Heads</th>
                <th>Submitted Date</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($pcicSubmissions)): ?>
                <tr><td colspan="9" class="empty-state"><i class="bi bi-inbox"></i><h4>No PCIC Insurance submissions yet</h4></td></tr>
              <?php else: foreach($pcicSubmissions as $row): 
                $stClass = strtolower($row['status']);
                $fNameClean = htmlspecialchars($row['farmer_name'] ?: ($row['applicant_name'] ?: 'Farmer'));
              ?>
                <tr class="review-row" data-search="<?= strtolower(htmlspecialchars(($row['application_no']??'').' '.$fNameClean.' '.($row['farm_location']??'').' '.($row['animal_type']??''))) ?>" data-status="<?= $stClass ?>">
                  <td style="font-weight:700;">#<?= $row['id'] ?></td>
                  <td><code style="font-weight:700;background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($row['application_no'] ?? 'N/A') ?></code></td>
                  <td><b><?= $fNameClean ?></b></td>
                  <td style="font-size:12px;"><?= htmlspecialchars($row['farm_location'] ?? 'N/A') ?></td>
                  <td><b><?= htmlspecialchars($row['animal_type'] ?? 'Mixed') ?></b></td>
                  <td><span class="badge" style="background:#ecfdf5;color:#065f46;font-weight:700;"><?= (int)$row['head_count'] ?> heads</span></td>
                  <td style="font-size:12px;white-space:nowrap;"><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                  <td>
                    <span class="status-pill status-<?= $stClass ?>"><?= ucfirst($stClass) ?></span>
                    <?php if(!empty($row['remarks']) && $stClass==='declined'): ?>
                      <div style="font-size:10px;color:#dc2626;max-width:180px;margin-top:2px;" title="<?= htmlspecialchars($row['remarks']) ?>"><b>Reason:</b> <?= htmlspecialchars(substr($row['remarks'], 0, 40)) ?>...</div>
                    <?php endif; ?>
                  </td>
                  <td style="text-align:right;white-space:nowrap;">
                    <button type="button" class="btn btn-sm btn-secondary btn-pdf-download" onclick="downloadSubmissionPdf('pcic', <?= $row['id'] ?>, '<?= addslashes($fNameClean) ?>', '<?= date('Ymd', strtotime($row['created_at'])) ?>')" title="Download PDF of PCIC Livestock Insurance Form"><i class="bi bi-file-earmark-pdf-fill"></i> Download PDF</button>
                    <?php if($stClass !== 'accepted'): ?>
                      <button type="button" class="btn btn-sm btn-success" onclick="acceptSubmission('pcic', <?= $row['id'] ?>, '<?= addslashes($fNameClean) ?>')" style="background:#10b981;color:#fff;border:none;"><i class="bi bi-check-lg"></i> Accept</button>
                    <?php endif; ?>
                    <?php if($stClass !== 'declined'): ?>
                      <button type="button" class="btn btn-sm btn-danger" onclick="openDeclineModal('pcic', <?= $row['id'] ?>, '<?= addslashes($fNameClean) ?>')" style="background:#ef4444;color:#fff;border:none;"><i class="bi bi-x-lg"></i> Decline</button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== INCIDENTS ===== -->
  <section id="sec-incidents" class="section">
  <div class="page-heading">
    <h1>Incident Management</h1>
    <p>Active incidents. Resolved/closed items move to Archive automatically.</p>
  </div>
 
  <!-- Active incidents table (same as before, but JS removes row on resolve) -->
  <div class="table-container" style="margin-bottom:24px;">
    <div class="table-header"><h2><i class="bi bi-exclamation-triangle"></i> Active Incidents</h2></div>
    <div class="table-scroll">
      <table>
        <thead><tr><th>Title</th><th>Farm</th><th>Type</th><th>Status</th><th>Priority</th><th>Actions</th></tr></thead>
        <tbody id="activeIncidentsBody">
          <?php foreach ($all_incidents as $incident):
            if (in_array($incident['status'], ['Resolved','Closed'])) continue; ?>
          <tr id="inc-row-<?= $incident['id'] ?>">
            <td style="font-weight:600;"><?= htmlspecialchars($incident['title']) ?></td>
            <td><?= htmlspecialchars($incident['farm_name'] ?? 'N/A') ?></td>
            <td><?= ucfirst($incident['type']) ?></td>
            <td><span class="status-badge status-<?= strtolower($incident['status']) ?>"><?= ucfirst(str_replace('_',' ',$incident['status'])) ?></span></td>
            <td>
              <select class="priority-select" onchange="updateIncidentPriority(<?= $incident['id'] ?>, this.value, this)" data-orig="<?= htmlspecialchars($incident['priority']) ?>">
                <option value="Low" <?= $incident['priority']==='Low'?'selected':'' ?>>Low</option>
                <option value="Medium" <?= $incident['priority']==='Medium'?'selected':'' ?>>Medium</option>
                <option value="High" <?= $incident['priority']==='High'?'selected':'' ?>>High</option>
                <option value="Critical" <?= $incident['priority']==='Critical'?'selected':'' ?>>Critical</option>
              </select>
            </td>
            <td>
              <div style="display:inline-flex;gap:6px;align-items:center;">
                <button type="button" class="an-btn" style="padding:.3rem .6rem;font-size:.75rem;background:#f8fafc;color:#1e293b;border:1px solid #cbd5e1;" onclick="viewIncidentDetails(<?= (int)$incident['id'] ?>)" title="View Incident Details">
                  <i class="bi bi-eye"></i> Details
                </button>
                <button class="btn btn-primary" onclick="resolveAndArchive(<?= $incident['id'] ?>, this)">
                  <i class="bi bi-check2"></i> Resolve
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
 
  <!-- Archive -->
  <div class="table-container">
    <div class="table-header" style="background:linear-gradient(135deg,#374151,#1f2937);">
      <h2><i class="bi bi-archive"></i> Archived Incidents</h2>
      <div style="display:flex;gap:6px;">
        <button class="btn btn-ghost" style="font-size:0.78rem;color:#fff;background:rgba(255,255,255,0.15);" onclick="downloadArchive('incidents','xlsx')"><i class="bi bi-file-earmark-excel-fill"></i> Excel</button>
        <button class="btn btn-ghost" style="font-size:0.78rem;color:#fff;background:rgba(255,255,255,0.15);" onclick="downloadArchive('incidents','pdf')"><i class="bi bi-file-earmark-pdf-fill"></i> PDF</button>
        <button class="btn btn-ghost" style="font-size:0.78rem;color:#fff;background:rgba(255,255,255,0.15);" onclick="downloadArchive('incidents','csv')"><i class="bi bi-filetype-csv"></i> CSV</button>
      </div>
    </div>
    <div class="table-scroll">
      <table id="archiveIncTable">
        <thead><tr><th>Title</th><th>Farm</th><th>Type</th><th>Priority</th><th>Resolved</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody id="archiveIncBody">
          <?php foreach ($archived_incidents as $inc): ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($inc['title']) ?></td>
            <td><?= htmlspecialchars($inc['farm_name'] ?? 'N/A') ?></td>
            <td><?= ucfirst($inc['type']) ?></td>
            <td><span class="status-badge priority-<?= strtolower($inc['priority']) ?>"><?= $inc['priority'] ?></span></td>
            <td style="color:var(--c-text-sub);font-size:0.82rem;"><?= $inc['resolvedAt'] ? date('M j, Y', strtotime($inc['resolvedAt'])) : ' - ' ?></td>
            <td style="text-align:right;">
              <button type="button" class="an-btn" style="padding:.25rem .6rem;font-size:.75rem;background:#f8fafc;color:#1e293b;border:1px solid #cbd5e1;" onclick="viewIncidentDetails(<?= (int)$inc['id'] ?>)" title="View Incident Details">
                <i class="bi bi-eye"></i> Details
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($archived_incidents)): ?>
          <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--c-text-sub);">No archived incidents yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

  <!-- ===== PUBLIC REPORTS ===== -->
  <section id="sec-public" class="section">
  <div class="page-heading">
    <h1>Public Reports</h1>
    <p>Pending/Reviewed reports below. Resolved/Closed move to Archive.</p>
  </div>
  <div class="table-container" style="margin-bottom:24px;">
    <div class="table-header"><h2><i class="bi bi-file-earmark-text"></i> Active Reports</h2></div>
    <div class="table-scroll">
      <table>
        <thead><tr><th>Type</th><th>Description</th><th>Contact</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($public_reports as $report):
            if (in_array($report['status'], ['Resolved','Closed'])) continue; ?>
          <tr id="rpt-row-<?= $report['id'] ?>">
            <td><span class="status-badge" style="background:#EDE9FE;color:#7C3AED;"><?= ucfirst($report['reportType']) ?></span></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($report['description']) ?>"><?= htmlspecialchars(substr($report['description'], 0, 60)) ?>...</td>
            <td style="font-size:0.85rem;"><?= htmlspecialchars($report['contactPhone']) ?></td>
            <td>
              <select class="status-select" onchange="updateReportStatusAndArchive(<?= $report['id'] ?>, this.value, this)">
                <option value="Pending" <?= $report['status']==='Pending'?'selected':'' ?>>Pending</option>
                <option value="Reviewed" <?= $report['status']==='Reviewed'?'selected':'' ?>>Reviewed</option>
                <option value="Resolved" <?= $report['status']==='Resolved'?'selected':'' ?>>Resolved</option>
                <option value="Closed" <?= $report['status']==='Closed'?'selected':'' ?>>Closed</option>
              </select>
            </td>
            <td style="color:var(--c-text-sub);font-size:0.82rem;"><?= date('M j, Y', strtotime($report['createdAt'])) ?></td>
            <td><button type="button" class="an-btn an-btn-primary" style="padding:.3rem .65rem;font-size:.76rem;" onclick="investigatePublicReport(<?= (int)$report['id'] ?>)"><i class="bi bi-search"></i> Investigate</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
 
  <div class="table-container">
    <div class="table-header" style="background:linear-gradient(135deg,#374151,#1f2937);">
      <h2><i class="bi bi-archive"></i> Archived Reports</h2>
      <div style="display:flex;gap:6px;">
        <button class="btn btn-ghost" style="font-size:0.78rem;color:#fff;background:rgba(255,255,255,0.15);" onclick="downloadArchive('reports','xlsx')"><i class="bi bi-file-earmark-excel-fill"></i> Excel</button>
        <button class="btn btn-ghost" style="font-size:0.78rem;color:#fff;background:rgba(255,255,255,0.15);" onclick="downloadArchive('reports','pdf')"><i class="bi bi-file-earmark-pdf-fill"></i> PDF</button>
        <button class="btn btn-ghost" style="font-size:0.78rem;color:#fff;background:rgba(255,255,255,0.15);" onclick="downloadArchive('reports','csv')"><i class="bi bi-filetype-csv"></i> CSV</button>
      </div>
    </div>
    <div class="table-scroll">
      <table id="archiveRptTable">
        <thead><tr><th>Type</th><th>Description</th><th>Contact</th><th>Status</th><th>Date</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody id="archiveRptBody">
          <?php foreach ($archived_reports as $rpt): ?>
          <tr>
            <td><span class="status-badge" style="background:#EDE9FE;color:#7C3AED;"><?= ucfirst($rpt['reportType']) ?></span></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars(substr($rpt['description'],0,60)) ?>...</td>
            <td style="font-size:0.85rem;"><?= htmlspecialchars($rpt['contactPhone']) ?></td>
            <td><span class="status-badge status-<?= strtolower($rpt['status']) ?>"><?= $rpt['status'] ?></span></td>
            <td style="color:var(--c-text-sub);font-size:0.82rem;"><?= date('M j, Y', strtotime($rpt['createdAt'])) ?></td>
            <td style="text-align:right;">
              <button type="button" class="an-btn" style="padding:.25rem .6rem;font-size:.75rem;background:#f8fafc;color:#1e293b;border:1px solid #cbd5e1;" onclick="investigatePublicReport(<?= (int)$rpt['id'] ?>)" title="View report details">
                <i class="bi bi-eye"></i> View
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($archived_reports)): ?>
          <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--c-text-sub);">No archived reports yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

  <!-- ===== GEO-MONITORING ===== -->
    <section id="sec-map" class="section">
    <!-- Map Header Island (matching farm-map.php top-nav-island) -->
    <div style="display:flex;align-items:center;justify-content:space-between;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:14px 20px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,0,0,0.03);flex-wrap:wrap;gap:12px;">
      
      <!-- Brand / Municipality Info -->
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:42px;height:42px;border-radius:12px;background:#ecfdf5;color:#10b981;display:flex;align-items:center;justify-content:center;font-size:1.35rem;">
          <i class="bi bi-geo-alt-fill"></i>
        </div>
        <div>
          <div style="font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px;">
            <span>AgriTrace+ <span style="color:#10b981;">GIS</span></span>
            <span style="font-size:0.68rem;background:#d1fae5;color:#065f46;border:1px solid rgba(16,185,129,0.3);padding:2px 7px;border-radius:99px;font-weight:700;"><i class="bi bi-circle-fill" style="font-size:0.45rem;"></i> LIVE <?= strtoupper(htmlspecialchars($officerMuni)) ?> MAP</span>
          </div>
          <div style="font-size:0.75rem;color:#64748b;"><?= htmlspecialchars($officerMuni) ?> Municipality &bull; Official Geo-Tagged Spatial Monitoring</div>
        </div>
      </div>

      <!-- Quick Stats HUD Pills -->
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:6px;padding:6px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-size:0.78rem;">
          <span style="width:8px;height:8px;border-radius:50%;background:#10b981;"></span>
          <span style="color:#64748b;">Farms (<?= htmlspecialchars($officerMuni) ?>):</span>
          <strong style="color:#0f172a;"><?= $muniFarmsCount ?></strong>
        </div>
        <div style="display:flex;align-items:center;gap:6px;padding:6px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-size:0.78rem;">
          <span style="width:8px;height:8px;border-radius:50%;background:#3b82f6;"></span>
          <span style="color:#64748b;">Livestock:</span>
          <strong style="color:#0f172a;"><?= number_format($muniLivestockCount) ?></strong>
        </div>
        <div style="display:flex;align-items:center;gap:6px;padding:6px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-size:0.78rem;">
          <span style="width:8px;height:8px;border-radius:50%;background:#ef4444;"></span>
          <span style="color:#64748b;">Active Alerts:</span>
          <strong style="color:#0f172a;"><?= $muniIncidentsCount ?></strong>
        </div>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:8px;align-items:center;">
        <a href="<?= app_url('maps/farm-map.php?municipality=' . urlencode($officerMuni)) ?>" target="_blank" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:10px;font-size:0.83rem;font-weight:700;text-decoration:none;box-shadow:0 2px 6px rgba(16,185,129,0.3);">
          <i class="bi bi-arrows-fullscreen"></i> Full Map View
        </a>
      </div>

    </div>

    <!-- Map Canvas Card with Responsive Iframe & Floating Bottom Legend Dock -->
    <div class="map-section-card" style="position:relative;background:#fff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.04);">
      <iframe class="map-frame-view" src="<?= app_url('maps/farm-map.php?municipality=' . urlencode($officerMuni)) ?>" title="<?= htmlspecialchars($officerMuni) ?> GIS Map" allow="fullscreen" allowfullscreen style="width:100%;height:680px;border:none;display:block;background:#f8fafc;"></iframe>
      
      <!-- Floating Bottom Legend Dock (identical to farm-map.php) -->
      <div style="position:absolute;bottom:16px;left:50%;transform:translateX(-50%);z-index:10;display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.92);backdrop-filter:blur(10px);border:1px solid #cbd5e1;border-radius:99px;padding:6px 14px;box-shadow:0 4px 16px rgba(0,0,0,0.12);max-width:94%;overflow-x:auto;white-space:nowrap;">
        <div style="display:inline-flex;align-items:center;gap:6px;font-size:0.75rem;font-weight:600;color:#334155;"><span style="width:10px;height:10px;border-radius:50%;background:#ef4444;box-shadow:0 0 6px rgba(239,68,68,0.7);display:inline-block;"></span> Suspected Outbreak</div>
        <div style="display:inline-flex;align-items:center;gap:6px;font-size:0.75rem;font-weight:600;color:#334155;"><span style="width:10px;height:10px;border-radius:50%;background:#f97316;box-shadow:0 0 6px rgba(249,115,22,0.6);display:inline-block;"></span> Sick Livestock</div>
        <div style="display:inline-flex;align-items:center;gap:6px;font-size:0.75rem;font-weight:600;color:#334155;"><span style="width:10px;height:10px;border-radius:50%;background:#10b981;box-shadow:0 0 6px rgba(16,185,129,0.6);display:inline-block;"></span> Verified Healthy Farm</div>
        <div style="display:inline-flex;align-items:center;gap:6px;font-size:0.75rem;font-weight:600;color:#334155;"><span style="width:10px;height:10px;border-radius:50%;background:#94a3b8;display:inline-block;"></span> Inactive / Unverified</div>
        <div style="display:inline-flex;align-items:center;gap:6px;font-size:0.75rem;font-weight:600;color:#334155;"><span style="width:12px;height:12px;border-radius:50%;border:2px dashed #ef4444;background:rgba(239,68,68,0.2);display:inline-block;"></span> 1km Quarantine</div>
        <div style="display:inline-flex;align-items:center;gap:6px;font-size:0.75rem;font-weight:600;color:#334155;"><span style="width:12px;height:12px;border-radius:50%;border:2px dashed #f59e0b;background:rgba(245,158,11,0.15);display:inline-block;"></span> 7km Surveillance</div>
      </div>
    </div>
  </section>

  <!-- ===== ANALYTICS ===== -->
  <section id="sec-reports" class="section">
  <?php
    $an_farms     = (int)$analytics['totals']['total_farms'];
    $an_heads     = (int)$analytics['totals']['total_livestock'];
    $an_inc_total = (int)$analytics['totals']['total_incidents'];
    $an_inc_act   = (int)$analytics['totals']['active_incidents'];
    $an_avg_heads = $an_farms > 0 ? round($an_heads / $an_farms, 1) : 0;
    $an_res_rate  = $an_inc_total > 0 ? round((($an_inc_total - $an_inc_act) / $an_inc_total) * 100) : 100;
    $an_types     = $analytics['livestock_by_type'];
    $an_max_type  = !empty($an_types) ? max(1, (int)max(array_column($an_types, 'total_qty'))) : 1;
    $an_fs        = $an_extra['farm_status'];
    $an_fs_total  = max(1, array_sum($an_fs));
    $an_is        = $an_extra['incident_status'];
    $an_is_total  = max(1, array_sum($an_is));
    $an_ip        = $an_extra['incident_priority'];
    $an_top_max   = !empty($an_extra['top_farms']) ? max(1, (int)max(array_column($an_extra['top_farms'], 'heads'))) : 1;
    $an_fs_colors = ['Approved' => '#10B981', 'Pending' => '#F59E0B', 'Rejected' => '#EF4444'];
    $an_is_colors = ['Pending' => '#F59E0B', 'In Progress' => '#3B82F6', 'Resolved' => '#10B981', 'Closed' => '#94A3B8'];
    $an_ip_colors = ['Low' => '#FBBF24', 'Medium' => '#F97316', 'High' => '#EF4444', 'Critical' => '#B91C1C'];
  ?>

  <!-- Hero header -->
  <div class="an-hero">
    <div class="an-hero-text">
      <span class="an-eyebrow"><i class="bi bi-bar-chart-line-fill"></i> Insights Center</span>
      <h1>Reports &amp; Analytics</h1>
      <p>Livestock population, distribution, incident health and yearly trends across the district.</p>
    </div>
    <div class="an-hero-actions">
      <span class="an-date-chip"><i class="bi bi-calendar3"></i> <?= date('F j, Y') ?></span>
      <button onclick="downloadReport('pdf')" class="an-btn an-btn-primary"><i class="bi bi-file-earmark-pdf-fill"></i> PDF</button>
      <button onclick="downloadReport('excel')" class="an-btn an-btn-ghost"><i class="bi bi-file-earmark-excel-fill"></i> Excel</button>
      <button onclick="downloadReport('csv')" class="an-btn an-btn-ghost"><i class="bi bi-filetype-csv"></i> CSV</button>
    </div>
  </div>

  <!-- KPI cards -->
  <div class="an-kpis">
    <div class="an-kpi" style="--kc:#10B981;--kb:#D1FAE5;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-house-heart-fill"></i></div>
        <span class="an-kpi-chip"><i class="bi bi-graph-up-arrow"></i> Registered</span>
      </div>
      <div class="an-kpi-val"><?= number_format($an_farms) ?></div>
      <div class="an-kpi-lbl">Total Farms</div>
      <div class="an-kpi-foot"><?= number_format($an_fs['Approved'] ?? 0) ?> approved &middot; <?= number_format($an_fs['Pending'] ?? 0) ?> pending</div>
    </div>
    <div class="an-kpi" style="--kc:#3B82F6;--kb:#DBEAFE;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-tags-fill"></i></div>
        <span class="an-kpi-chip"><i class="bi bi-diagram-3-fill"></i> <?= count($an_types) ?> types</span>
      </div>
      <div class="an-kpi-val"><?= number_format($an_heads) ?></div>
      <div class="an-kpi-lbl">Total Livestock</div>
      <div class="an-kpi-foot">Avg. <?= $an_avg_heads ?> heads per farm</div>
    </div>
    <div class="an-kpi" style="--kc:#F59E0B;--kb:#FEF3C7;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <span class="an-kpi-chip"><i class="bi bi-check2-circle"></i> <?= $an_res_rate ?>% resolved</span>
      </div>
      <div class="an-kpi-val"><?= number_format($an_inc_total) ?></div>
      <div class="an-kpi-lbl">Total Incidents</div>
      <div class="an-kpi-foot"><?= number_format($an_inc_total - $an_inc_act) ?> closed out</div>
    </div>
    <div class="an-kpi" style="--kc:#EF4444;--kb:#FEE2E2;">
      <div class="an-kpi-top">
        <div class="an-kpi-icon"><i class="bi bi-activity"></i></div>
        <span class="an-kpi-chip"><i class="bi bi-broadcast"></i> Live</span>
      </div>
      <div class="an-kpi-val"><?= number_format($an_inc_act) ?></div>
      <div class="an-kpi-lbl">Active Incidents</div>
      <div class="an-kpi-foot">Need attention now</div>
    </div>
  </div>

  <!-- Composition + Trend -->
  <div class="an-grid an-grid-5-7">
    <div class="an-card">
      <div class="an-card-head">
        <div><h3><i class="bi bi-pie-chart-fill"></i> Livestock Composition</h3><p>Share of heads by animal type</p></div>
      </div>
      <?php if (!empty($an_types)): ?>
      <div class="an-donut-wrap">
        <div class="an-donut-canvas">
          <canvas id="animalDonutChart"></canvas>
          <div class="an-donut-center"><strong><?= number_format($an_heads) ?></strong><span>Total heads</span></div>
        </div>
        <div class="an-legend">
          <?php foreach ($an_types as $lt):
            $t   = $lt['type'];
            $col = $type_colors[$t] ?? '#64748B';
            $pc  = $an_heads > 0 ? round(($lt['total_qty'] / $an_heads) * 100, 1) : 0;
          ?>
          <div class="an-legend-row">
            <span class="an-dot" style="background:<?= $col ?>;"></span>
            <span class="an-legend-name"><?= htmlspecialchars($type_display[$t] ?? $t) ?></span>
            <span class="an-legend-pct"><?= $pc ?>%</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="an-empty"><i class="bi bi-inbox"></i><p>No livestock data available yet.</p></div>
      <?php endif; ?>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div><h3><i class="bi bi-graph-up-arrow"></i> Yearly Registration Trend</h3><p>Per animal type &middot; last 5 years</p></div>
      </div>
      <div class="an-trend-canvas"><canvas id="livestockTrendChart"></canvas></div>
    </div>
  </div>

  <!-- Ranked breakdown + Regional -->
  <div class="an-grid an-grid-7-5">
    <div class="an-card">
      <div class="an-card-head">
        <div><h3><i class="bi bi-bar-chart-horizontal-fill"></i> Livestock by Animal Type</h3><p>Ranked by total heads</p></div>
      </div>
      <?php if (!empty($an_types)): ?>
      <div class="an-rank-list">
        <?php foreach ($an_types as $i => $lt):
          $t     = $lt['type'];
          $col   = $type_colors[$t] ?? '#64748B';
          $emoji = $type_icons[$t] ?? '<i class="bi bi-tag-fill"></i>';
          $pct   = round(($lt['total_qty'] / $an_max_type) * 100);
          $share = $an_heads > 0 ? round(($lt['total_qty'] / $an_heads) * 100, 1) : 0;
        ?>
        <div class="an-rank-row">
          <span class="an-rank-no"><?= $i + 1 ?></span>
          <span class="an-rank-emoji" style="background:<?= $col ?>1A;"><?= $emoji ?></span>
          <div class="an-rank-body">
            <div class="an-rank-top">
              <strong><?= htmlspecialchars($type_display[$t] ?? $t) ?></strong>
              <span><b><?= number_format($lt['total_qty']) ?></b> heads &middot; <?= number_format($lt['farm_count']) ?> farms</span>
            </div>
            <div class="an-bar-track"><div class="an-bar-fill" data-pct="<?= $pct ?>" style="width:0%;background:linear-gradient(90deg,<?= $col ?>,<?= $col ?>CC);"></div></div>
          </div>
          <span class="an-rank-share"><?= $share ?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="an-empty"><i class="bi bi-inbox"></i><p>No livestock data available yet.</p></div>
      <?php endif; ?>
    </div>

    <div class="an-card">
      <div class="an-card-head">
        <div><h3><i class="bi bi-geo-alt-fill"></i> Regional Distribution</h3><p>Top areas by livestock</p></div>
      </div>
      <?php if (!empty($analytics['regional_stats'])): ?>
      <div class="an-region-list">
        <?php foreach ($analytics['regional_stats'] as $region):
          $rp = $an_heads > 0 ? round(($region['livestock_count'] / $an_heads) * 100, 1) : 0;
        ?>
        <div class="an-region">
          <div class="an-region-top">
            <div class="an-region-name"><span class="an-region-pin"><i class="bi bi-geo-alt-fill"></i></span><?= htmlspecialchars($region['region']) ?></div>
            <span class="an-region-pct"><?= $rp ?>%</span>
          </div>
          <div class="an-bar-track an-bar-thin"><div class="an-bar-fill" data-pct="<?= min(100, $rp) ?>" style="width:0%;background:linear-gradient(90deg,#10B981,#059669);"></div></div>
          <div class="an-region-meta"><span><i class="bi bi-house-door"></i> <?= number_format($region['farm_count']) ?> farms</span><span><i class="bi bi-tags"></i> <?= number_format($region['livestock_count']) ?> heads</span></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="an-empty"><i class="bi bi-inbox"></i><p>No regional data available.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Health overview: farms, incidents, top farms -->
  <div class="an-grid an-grid-3">
    <div class="an-card">
      <div class="an-card-head"><div><h3><i class="bi bi-patch-check-fill"></i> Farm Approval Status</h3><p><?= number_format(array_sum($an_fs)) ?> farms total</p></div></div>
      <div class="an-seg">
        <?php foreach ($an_fs as $k => $v): ?>
        <span style="width:<?= round(($v / $an_fs_total) * 100, 2) ?>%;background:<?= $an_fs_colors[$k] ?? '#94A3B8' ?>;" title="<?= htmlspecialchars($k) ?>: <?= $v ?>"></span>
        <?php endforeach; ?>
      </div>
      <div class="an-stat-list">
        <?php foreach ($an_fs as $k => $v): ?>
        <div class="an-stat-row"><span class="an-dot" style="background:<?= $an_fs_colors[$k] ?? '#94A3B8' ?>;"></span><span class="an-stat-name"><?= htmlspecialchars($k) ?></span><strong><?= number_format($v) ?></strong><em><?= round(($v / $an_fs_total) * 100) ?>%</em></div>
        <?php endforeach; ?>
        <?php if (empty($an_fs)): ?><div class="an-empty an-empty-sm"><p>No farms registered.</p></div><?php endif; ?>
      </div>
    </div>

    <div class="an-card">
      <div class="an-card-head"><div><h3><i class="bi bi-shield-exclamation"></i> Incident Overview</h3><p><?= $an_res_rate ?>% resolution rate</p></div></div>
      <div class="an-seg">
        <?php foreach ($an_is as $k => $v): ?>
        <span style="width:<?= round(($v / $an_is_total) * 100, 2) ?>%;background:<?= $an_is_colors[$k] ?? '#94A3B8' ?>;" title="<?= htmlspecialchars($k) ?>: <?= $v ?>"></span>
        <?php endforeach; ?>
      </div>
      <div class="an-stat-list">
        <?php foreach ($an_is as $k => $v): ?>
        <div class="an-stat-row"><span class="an-dot" style="background:<?= $an_is_colors[$k] ?? '#94A3B8' ?>;"></span><span class="an-stat-name"><?= htmlspecialchars($k) ?></span><strong><?= number_format($v) ?></strong><em><?= round(($v / $an_is_total) * 100) ?>%</em></div>
        <?php endforeach; ?>
        <?php if (empty($an_is)): ?><div class="an-empty an-empty-sm"><p>No incidents recorded.</p></div><?php endif; ?>
      </div>
      <?php if (!empty($an_ip)): ?>
      <div class="an-chips">
        <?php foreach ($an_ip as $k => $v): ?>
        <span class="an-chip" style="--cc:<?= $an_ip_colors[$k] ?? '#64748B' ?>;"><i></i><?= htmlspecialchars($k) ?> <b><?= number_format($v) ?></b></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="an-card">
      <div class="an-card-head"><div><h3><i class="bi bi-trophy-fill"></i> Top Farms</h3><p>Largest herds registered</p></div></div>
      <div class="an-top-list">
        <?php foreach ($an_extra['top_farms'] as $i => $tf):
          $tp = round(($tf['heads'] / $an_top_max) * 100);
        ?>
        <div class="an-top-row">
          <span class="an-medal an-medal-<?= min($i + 1, 4) ?>"><?= $i + 1 ?></span>
          <div class="an-top-body">
            <div class="an-top-name"><?= htmlspecialchars($tf['name'] ?? 'Unnamed Farm') ?></div>
            <div class="an-bar-track an-bar-thin"><div class="an-bar-fill" data-pct="<?= $tp ?>" style="width:0%;background:linear-gradient(90deg,#3B82F6,#2563EB);"></div></div>
          </div>
          <strong class="an-top-val"><?= number_format($tf['heads']) ?></strong>
        </div>
        <?php endforeach; ?>
        <?php if (empty($an_extra['top_farms'])): ?><div class="an-empty an-empty-sm"><p>No farms yet.</p></div><?php endif; ?>
      </div>
    </div>
  </div>
</section>

  <!-- ===== PROFILE ===== -->
  <section id="sec-profile" class="section">
    <div class="profile-card-wrapper">
      
      <!-- Top Green Header -->
      <div class="profile-card-header">
        <div class="profile-avatar-circle" id="daAvatarDisplay">
          <?php if (!empty($user_profile['profile_pix']) && file_exists($user_profile['profile_pix'])): ?>
            <img src="<?= htmlspecialchars($user_profile['profile_pix']) ?>" alt="Officer Profile">
          <?php else: ?>
            <?= strtoupper(substr($user_profile['firstName'] ?? 'U', 0, 1)) ?>
          <?php endif; ?>
        </div>
        <h2 class="profile-header-name"><?= htmlspecialchars(($user_profile['firstName']??'').' '.($user_profile['lastName']??'')) ?></h2>
        <div class="profile-header-role">
          <span><?= htmlspecialchars($user_role) ?></span>
          <?php if($user_role === 'Admin'): ?>
            <span class="profile-verified-chip"><i class="bi bi-shield-check"></i> Root Authority</span>
          <?php else: ?>
            <span class="profile-verified-chip"><i class="bi bi-patch-check-fill"></i> DA Verified</span>
          <?php endif; ?>
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
            <?= !empty($user_profile['email']) ? htmlspecialchars($user_profile['email']) : ' - ' ?>
            <?php if(!empty($user_profile['email'])): ?>
              <button type="button" class="profile-copy-btn" onclick="copyTagId('<?= htmlspecialchars(addslashes($user_profile['email'])) ?>', this)" title="Copy Email"><i class="bi bi-copy"></i></button>
            <?php endif; ?>
          </span>
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Mobile</span>
          <span class="profile-info-value">
            <?= !empty($user_profile['mobile']) ? htmlspecialchars($user_profile['mobile']) : ' - ' ?>
            <?php if(!empty($user_profile['mobile'])): ?>
              <button type="button" class="profile-copy-btn" onclick="copyTagId('<?= htmlspecialchars(addslashes($user_profile['mobile'])) ?>', this)" title="Copy Mobile"><i class="bi bi-copy"></i></button>
            <?php endif; ?>
          </span>
        </div>

        <?php if ($user_role === 'Admin'): ?>
        <!-- System Governance & Security Scope (Admin) -->
        <div class="profile-sec-heading">
          <i class="bi bi-shield-lock-fill"></i> Governance &amp; Security
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Clearance</span>
          <span class="profile-info-value" style="color:#059669; font-weight:700;"><i class="bi bi-shield-check"></i> Level 4 District Superuser</span>
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Jurisdiction</span>
          <span class="profile-info-value">Camarines Sur 1st District</span>
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Covered LGUs</span>
          <span class="profile-info-value">Cabusao, Del Gallego, Lupi, Ragay, Sipocot</span>
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Audit Logging</span>
          <span class="profile-info-value" style="color:#2563eb; font-weight:600;"><i class="bi bi-activity"></i> Active &amp; Recorded</span>
        </div>

        <!-- Assignment Scope -->
        <div class="profile-sec-heading">
          <i class="bi bi-geo-alt-fill"></i> Assignment
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Region</span>
          <span class="profile-info-value">Region V (Bicol Region)</span>
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Province</span>
          <span class="profile-info-value">Camarines Sur</span>
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Municipality</span>
          <span class="profile-info-value">1st District District-Wide</span>
        </div>
        <?php else: ?>
        <!-- Work Information (Agriculture Official) -->
        <div class="profile-sec-heading">
          <i class="bi bi-briefcase-fill"></i> Work Information
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Gov't ID</span>
          <span class="profile-info-value">
            <?= !empty($user_profile['gov_id']) ? htmlspecialchars($user_profile['gov_id']) : ' - ' ?>
            <?php if(!empty($user_profile['gov_id'])): ?>
              <button type="button" class="profile-copy-btn" onclick="copyTagId('<?= htmlspecialchars(addslashes($user_profile['gov_id'])) ?>', this)" title="Copy Gov ID"><i class="bi bi-copy"></i></button>
            <?php endif; ?>
          </span>
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Department</span>
          <span class="profile-info-value"><?= !empty($user_profile['department']) ? htmlspecialchars($user_profile['department']) : ' - ' ?></span>
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Position</span>
          <span class="profile-info-value"><?= !empty($user_profile['position']) ? htmlspecialchars($user_profile['position']) : ' - ' ?></span>
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Office</span>
          <span class="profile-info-value"><?= !empty($user_profile['office']) ? htmlspecialchars($user_profile['office']) : ' - ' ?></span>
        </div>

        <!-- Assignment -->
        <div class="profile-sec-heading">
          <i class="bi bi-geo-alt-fill"></i> Assignment
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Region</span>
          <span class="profile-info-value"><?= !empty($user_profile['assigned_region']) ? htmlspecialchars($user_profile['assigned_region']) : ' - ' ?></span>
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Province</span>
          <span class="profile-info-value"><?= !empty($user_profile['province']) ? htmlspecialchars($user_profile['province']) : ' - ' ?></span>
        </div>
        <div class="profile-info-row">
          <span class="profile-info-label">Municipality</span>
          <span class="profile-info-value"><?= !empty($user_profile['municipality']) ? htmlspecialchars($user_profile['municipality']) : ' - ' ?></span>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="profile-card-actions">
          <button type="button" class="profile-btn-edit" onclick="openEditModal()">
            <i class="bi bi-pencil-square"></i> Edit Profile
          </button>
          <button type="button" class="profile-btn-pw" onclick="openPasswordModal()">
            <i class="bi bi-shield-lock"></i> Change Password
          </button>
        </div>

      </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal-overlay">
      <div class="modal-content" style="max-width:580px;">
        <div class="modal-header">
          <h3><i class="bi bi-person-gear"></i> Edit Officer Credentials</h3>
          <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
          <form id="profileForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="user_id" value="<?= $user_id ?>">
            
            <!-- Live Avatar Selection Row -->
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;padding:0.85rem;background:#f8fafc;border-radius:14px;border:1px solid var(--c-slate-200);">
              <div class="da-preview-avatar" id="daEditLiveAvatar">
                <?php if (!empty($user_profile['profile_pix']) && file_exists($user_profile['profile_pix'])): ?>
                  <img src="<?= htmlspecialchars($user_profile['profile_pix']) ?>" alt="Preview">
                <?php else: ?>
                  <i class="bi bi-person-fill"></i>
                <?php endif; ?>
              </div>
              <div style="flex:1;">
                <label class="form-label" style="margin-bottom:3px;font-size:0.8rem;">Profile Photo</label>
                <input class="form-input" type="file" name="profile_pic" accept="image/*" onchange="previewDaPhoto(this)" style="padding:0.4rem;font-size:0.78rem;">
                <div style="font-size:0.68rem;color:var(--c-text-sub);margin-top:3px;">JPG, PNG up to 5MB</div>
              </div>
            </div>

            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">First Name *</label>
                <input class="form-input" type="text" name="firstName" value="<?= htmlspecialchars($user_profile['firstName']??'') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Last Name *</label>
                <input class="form-input" type="text" name="lastName" value="<?= htmlspecialchars($user_profile['lastName']??'') ?>" required>
              </div>
            </div>

            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">Mobile Contact</label>
                <input class="form-input" type="text" name="mobile" value="<?= htmlspecialchars($user_profile['mobile']??'') ?>" placeholder="+63 9XX XXX XXXX">
              </div>
              <div class="form-group">
                <label class="form-label">Government ID No.</label>
                <input class="form-input" type="text" name="gov_id" value="<?= htmlspecialchars($user_profile['gov_id']??'') ?>" placeholder="e.g. DA-BAI-2024-001">
              </div>
            </div>

            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">Department / Bureau</label>
                <input class="form-input" type="text" name="department" value="<?= htmlspecialchars($user_profile['department']??'') ?>" placeholder="Bureau of Animal Industry">
              </div>
              <div class="form-group">
                <label class="form-label">Position / Designation</label>
                <input class="form-input" type="text" name="position" value="<?= htmlspecialchars($user_profile['position']??'') ?>" placeholder="Agricultural Technologist II">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Field Station / Office</label>
              <input class="form-input" type="text" name="office" value="<?= htmlspecialchars($user_profile['office']??'') ?>" placeholder="Provincial Agriculture Office, Pili, Camarines Sur">
            </div>

            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">Administrative Region</label>
                <input class="form-input" type="text" name="assigned_region" value="<?= htmlspecialchars($user_profile['assigned_region']??'') ?>" placeholder="Region V - Bicol">
              </div>
              <div class="form-group">
                <label class="form-label">Province</label>
                <input class="form-input" type="text" name="province" value="<?= htmlspecialchars($user_profile['province']??'') ?>" placeholder="Camarines Sur">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Municipality / Assigned Area</label>
              <input class="form-input" type="text" name="municipality" value="<?= htmlspecialchars($user_profile['municipality']??'') ?>" placeholder="e.g. Pili, Naga City, Iriga City">
            </div>

            <div class="form-group">
              <label class="form-label">Set New Password <span style="color:var(--c-text-sub);font-weight:400;">(leave blank to keep current)</span></label>
              <input class="form-input" type="password" name="new_password" placeholder="********">
            </div>
          </form>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
          <button type="button" class="btn-save" onclick="submitProfile()"><i class="bi bi-check2"></i> Save Changes</button>
        </div>
      </div>
    </div>

    <!-- Change Password Modal -->
    <div id="passwordModal" class="modal-overlay">
      <div class="modal-content" style="max-width:460px;">
        <div class="modal-header">
          <h3><i class="bi bi-shield-lock"></i> Change Password</h3>
          <button class="modal-close" onclick="closePasswordModal()">&times;</button>
        </div>
        <div class="modal-body">
          <form id="daOfficerPwForm" autocomplete="off">
            <input type="hidden" name="action" value="change_officer_password">
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
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:1.5rem;">
              <button type="button" class="btn-cancel" onclick="closePasswordModal()">Cancel</button>
              <button type="submit" class="btn-save" id="btnUpdateOfficerPw"><i class="bi bi-key-fill"></i> Update Password</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>

<!-- VIEW FORM MODAL (8.5 x 13 FOLIO READ-ONLY EXACT VIEW) -->
<div id="daViewFormModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(4px);z-index:9000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;width:95%;max-width:9.2in;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
      <div>
        <h3 id="daViewModalTitle" style="font-size:1.1rem;font-weight:700;color:#0f172a;margin:0;">Official Form Submission View</h3>
        <span id="daViewModalSubtitle" style="font-size:.78rem;color:#64748b;">Read-only farmer submission</span>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn btn-sm btn-secondary" onclick="printDaViewForm()"><i class="bi bi-printer-fill"></i> Print</button>
        <button type="button" class="btn btn-sm btn-secondary" id="btnDaDownloadPdf"><i class="bi bi-file-earmark-pdf-fill"></i> Download PDF</button>
        <button type="button" style="background:#e2e8f0;border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#475569;" onclick="closeDaViewModal()">&times;</button>
      </div>
    </div>
    <div class="modal-folio-viewport" id="daViewModalViewport">
      <div id="daViewModalContent"></div>
    </div>
  </div>
</div>

<!-- DECLINE REMARKS MODAL -->
<div id="daDeclineModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(4px);z-index:9100;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;width:90%;max-width:480px;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);overflow:hidden;">
    <div style="padding:1.125rem 1.25rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#fef2f2;">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:50%;background:#fee2e2;color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:1.2rem;"><i class="bi bi-x-circle-fill"></i></div>
        <h3 style="font-size:1rem;font-weight:700;color:#991b1b;margin:0;">Decline Form Submission</h3>
      </div>
      <button type="button" style="background:transparent;border:none;font-size:1.4rem;color:#64748b;cursor:pointer;" onclick="closeDaDeclineModal()">&times;</button>
    </div>
    <div style="padding:1.25rem;">
      <p style="font-size:.875rem;color:#334155;margin-bottom:.75rem;">You are declining the application of <b id="declineFarmerName">Farmer</b>. Please provide a formal reason / remarks below (this will be logged and notified to the farmer):</p>
      <input type="hidden" id="declineFormType" value="">
      <input type="hidden" id="declineSubmissionId" value="">
      <label style="font-size:.8rem;font-weight:700;color:#475569;display:block;margin-bottom:4px;">Official Remarks / Reason *</label>
      <textarea id="declineRemarks" rows="4" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:.5rem;font-size:.85rem;font-family:inherit;outline:none;" placeholder="Specify why the submission was declined (e.g. Incomplete requirements, non-qualifying farm parcel, unverified animal ear tag)..." required></textarea>
    </div>
    <div style="padding:.875rem 1.25rem;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;">
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeDaDeclineModal()">Cancel</button>
      <button type="button" class="btn btn-danger btn-sm" id="btnConfirmDecline" onclick="submitDeclineReview()" style="background:#ef4444;color:#fff;border:none;"><i class="bi bi-x-octagon-fill"></i> Confirm Decline</button>
    </div>
  </div>
</div>

<!-- FARMER DETAILS & APPLICATIONS MODAL -->
<div id="farmerDetailsModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(4px);z-index:9000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:18px;width:95%;max-width:820px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);">
    <div style="padding:1.1rem 1.4rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:42px;height:42px;border-radius:12px;background:#e0e7ff;color:#4338ca;display:flex;align-items:center;justify-content:center;font-size:1.3rem;">
          <i class="bi bi-person-vcard-fill"></i>
        </div>
        <div>
          <h3 id="fdModalName" style="font-size:1.15rem;font-weight:800;color:#0f172a;margin:0;">Farmer Name</h3>
          <span id="fdModalSubtitle" style="font-size:.8rem;color:#64748b;">User ID # - Camarines Sur District 1</span>
        </div>
      </div>
      <button type="button" style="background:#e2e8f0;border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#475569;" onclick="closeFarmerDetails()">&times;</button>
    </div>
    <div style="padding:1.25rem 1.4rem;overflow-y:auto;flex:1 1 auto;" id="fdModalContent">
      <!-- Populated dynamically via JS -->
    </div>
    <div style="padding:.9rem 1.4rem;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <div id="fdModalFooterLeft" style="display:flex;gap:8px;flex-wrap:wrap;"></div>
      <div style="display:flex;gap:8px;">
        <button type="button" class="an-btn" style="background:#e2e8f0;color:#334155;" onclick="closeFarmerDetails()">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- DA ADD LIVESTOCK MODAL -->
<div id="daAddLivestockModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(4px);z-index:9050;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:18px;width:95%;max-width:680px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);">
    <div style="padding:1.1rem 1.4rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:40px;height:40px;border-radius:12px;background:#d1fae5;color:#065f46;display:flex;align-items:center;justify-content:center;font-size:1.25rem;">
          <i class="bi bi-tag-fill"></i>
        </div>
        <div>
          <h3 style="font-size:1.15rem;font-weight:800;color:#0f172a;margin:0;">Register / Add Livestock</h3>
          <span style="font-size:.8rem;color:#64748b;">Record individual ear-tagged animal under district farm</span>
        </div>
      </div>
      <button type="button" style="background:#e2e8f0;border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#475569;" onclick="closeDaAddLivestockModal()">&times;</button>
    </div>
    <form id="daAddLivestockForm" style="display:flex;flex-direction:column;flex:1 1 auto;overflow-y:auto;">
      <input type="hidden" name="action" value="add_livestock">
      <div style="padding:1.25rem 1.4rem;display:flex;flex-direction:column;gap:1.1rem;">
        
        <!-- Farm Selection -->
        <div class="form-group">
          <label class="form-label" style="font-weight:700;color:#1e293b;margin-bottom:4px;display:block;">Registered Farm Facility *</label>
          <select name="farm_id" id="daLvFarmSelect" class="form-input" required style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.88rem;">
            <option value="">-- Select Farm Facility --</option>
            <?php foreach($all_farms as $fmOpt): ?>
              <option value="<?= $fmOpt['id'] ?>"><?= htmlspecialchars($fmOpt['name']) ?> (<?= htmlspecialchars($fmOpt['pet_municipality'] ?? 'District 1') ?>) - <?= htmlspecialchars(trim(($fmOpt['firstName']??'').' '.($fmOpt['lastName']??''))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Operation Type (Backyard vs Commercial) -->
        <div>
          <label class="form-label" style="font-weight:700;color:#1e293b;margin-bottom:6px;display:block;">Operation Type *</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <label style="border:1.5px solid #cbd5e1;border-radius:10px;padding:10px 14px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:all .15s;" id="daFarmingTypeLblBackyard">
              <input type="radio" name="farming_type" value="Backyard" checked onchange="updateDaFarmingTypeVisual()" style="accent-color:#059669;width:17px;height:17px;">
              <div>
                <strong style="display:block;font-size:.88rem;color:#0f172a;">Backyard</strong>
                <small style="color:#64748b;font-size:.74rem;">Small-scale / household subsistence</small>
              </div>
            </label>
            <label style="border:1.5px solid #cbd5e1;border-radius:10px;padding:10px 14px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:all .15s;" id="daFarmingTypeLblCommercial">
              <input type="radio" name="farming_type" value="Commercial" onchange="updateDaFarmingTypeVisual()" style="accent-color:#059669;width:17px;height:17px;">
              <div>
                <strong style="display:block;font-size:.88rem;color:#0f172a;">Commercial</strong>
                <small style="color:#64748b;font-size:.74rem;">Registered intensive agricultural enterprise</small>
              </div>
            </label>
          </div>
        </div>

        <!-- Species & Breed -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
          <div class="form-group">
            <label class="form-label" style="font-weight:700;color:#1e293b;margin-bottom:4px;display:block;">Animal Species *</label>
            <select name="type" id="daLvSpeciesSelect" class="form-input" required onchange="onDaSpeciesChange()" style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.88rem;">
              <option value="">-- Select Species --</option>
              <option value="Cattle">Cattle</option>
              <option value="Carabao">Carabao</option>
              <option value="Swine">Swine / Hog</option>
              <option value="Goat">Goat</option>
              <option value="Sheep">Sheep</option>
              <option value="Poultry">Poultry / Chicken / Duck</option>
              <option value="Horse">Horse</option>
              <option value="Other">Other Livestock</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-weight:700;color:#1e293b;margin-bottom:4px;display:block;">Breed (Optional)</label>
            <input type="text" name="breed" class="form-input" placeholder="e.g. Brahman, Landrace, Native" style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.88rem;">
          </div>
        </div>

        <!-- Individual Ear Tag ID (Auto-Gen + Manual + Scanner) -->
        <div style="background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:12px;padding:12px 14px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <label class="form-label" style="font-weight:700;color:#0f172a;margin:0;font-size:.86rem;">
              <i class="bi bi-upc-scan" style="color:#059669;"></i> Individual Ear Tag / Physical ID *
            </label>
            <span style="font-size:.72rem;color:#64748b;">Physical ear tag or BAI barcode</span>
          </div>
          <div style="display:flex;gap:8px;">
            <input type="text" name="tagId" id="daLvTagId" class="form-input" required placeholder="Enter or scan physical tag number" style="flex:1;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.9rem;font-weight:700;font-family:monospace;letter-spacing:0.5px;">
            <button type="button" class="an-btn" onclick="generateDaTagId()" title="Automatically generate compliant ID" style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;font-size:.8rem;white-space:nowrap;padding:0 .85rem;">
              <i class="bi bi-magic"></i> Auto-Gen
            </button>
            <button type="button" class="an-btn" onclick="openDaTagScanner()" title="Scan camera barcode or QR" style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;font-size:.8rem;white-space:nowrap;padding:0 .85rem;">
              <i class="bi bi-camera-fill"></i> Scan
            </button>
          </div>
          <small style="display:block;margin-top:5px;font-size:.72rem;color:#64748b;">
            Enter existing ear tag number on animal, click <b>Auto-Gen</b> to generate standard BAI format, or click <b>Scan</b> to use device camera.
          </small>
        </div>

        <!-- Qty, Age, Weight -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;">
          <div class="form-group">
            <label class="form-label" style="font-weight:700;color:#1e293b;margin-bottom:4px;display:block;">Heads (Qty)</label>
            <input type="number" name="qty" class="form-input" value="1" min="1" max="9999" required style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.88rem;">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-weight:700;color:#1e293b;margin-bottom:4px;display:block;">Age (months)</label>
            <input type="number" step="0.1" name="age" class="form-input" placeholder="e.g. 18" style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.88rem;">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-weight:700;color:#1e293b;margin-bottom:4px;display:block;">Weight (kg)</label>
            <input type="number" step="0.1" name="weight" class="form-input" placeholder="e.g. 350" style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.88rem;">
          </div>
        </div>

        <!-- Health Condition & Vaccination -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
          <div class="form-group">
            <label class="form-label" style="font-weight:700;color:#1e293b;margin-bottom:4px;display:block;">Health Condition</label>
            <select name="healthStatus" class="form-input" style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.88rem;">
              <option value="Healthy">Healthy</option>
              <option value="Sick">Sick / Under Observation</option>
              <option value="Quarantined">Quarantined</option>
              <option value="Deceased">Deceased</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-weight:700;color:#1e293b;margin-bottom:4px;display:block;">Vaccination Status</label>
            <select name="vaccinationStatus" class="form-input" style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.88rem;">
              <option value="None">None Recorded</option>
              <option value="Partial">Partial (Initial dose)</option>
              <option value="Up to Date">Up to Date (Complete)</option>
            </select>
          </div>
        </div>

        <!-- Notes -->
        <div class="form-group">
          <label class="form-label" style="font-weight:700;color:#1e293b;margin-bottom:4px;display:block;">Inspection Remarks / Notes</label>
          <textarea name="notes" rows="2" class="form-input" placeholder="Physical marks, ear notches, vaccination batch numbers..." style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.88rem;"></textarea>
        </div>

      </div>
      <div style="padding:.9rem 1.4rem;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="an-btn" style="background:#e2e8f0;color:#334155;" onclick="closeDaAddLivestockModal()">Cancel</button>
        <button type="submit" class="an-btn an-btn-primary" id="btnSaveDaLv"><i class="bi bi-check2-circle"></i> Save Livestock Record</button>
      </div>
    </form>
  </div>
</div>

<!-- DA TAG CAMERA / BARCODE SCANNER MODAL -->
<div id="daTagScanModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);backdrop-filter:blur(6px);z-index:9150;align-items:center;justify-content:center;">
  <div style="background:#0f172a;border-radius:20px;width:92%;max-width:480px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.7);border:1px solid rgba(255,255,255,.1);">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.1);display:flex;justify-content:space-between;align-items:center;">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:34px;height:34px;border-radius:50%;background:rgba(16,185,129,.2);color:#10b981;display:flex;align-items:center;justify-content:center;font-size:1.1rem;"><i class="bi bi-upc-scan"></i></div>
        <h3 style="color:#fff;font-size:1rem;font-weight:700;margin:0;">Scan Physical Ear Tag</h3>
      </div>
      <button type="button" style="background:transparent;border:none;color:#94a3b8;font-size:1.3rem;cursor:pointer;" onclick="closeDaTagScanner()">&times;</button>
    </div>
    <div style="position:relative;background:#000;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;overflow:hidden;">
      <video id="daTagVideo" playsinline autoplay muted style="width:100%;height:100%;object-fit:cover;"></video>
      <div style="position:absolute;inset:20% 15%;border:2px solid #10b981;border-radius:12px;box-shadow:0 0 0 9999px rgba(0,0,0,0.5);pointer-events:none;">
        <div style="position:absolute;top:50%;left:5%;right:5%;height:2px;background:#ef4444;box-shadow:0 0 8px #ef4444;"></div>
      </div>
      <div id="daScanStatusMsg" style="position:absolute;bottom:12px;left:12px;right:12px;text-align:center;color:#fff;font-size:.76rem;background:rgba(0,0,0,.65);padding:6px 10px;border-radius:20px;">
        Point camera at livestock ear tag barcode or QR
      </div>
    </div>
    <div style="padding:1rem 1.25rem;display:flex;gap:8px;justify-content:space-between;align-items:center;background:#1e293b;">
      <div style="display:flex;gap:6px;">
        <button type="button" class="an-btn" onclick="switchDaTagCam()" style="background:#334155;color:#fff;font-size:.78rem;padding:.4rem .7rem;">
          <i class="bi bi-arrow-repeat"></i> Flip
        </button>
        <label class="an-btn" style="background:#334155;color:#fff;font-size:.78rem;padding:.4rem .7rem;cursor:pointer;margin:0;">
          <i class="bi bi-image"></i> Photo <input type="file" accept="image/*" style="display:none;" onchange="handleDaTagImageUpload(this)">
        </label>
      </div>
      <button type="button" class="an-btn" onclick="closeDaTagScanner()" style="background:#ef4444;color:#fff;font-size:.78rem;padding:.4rem .9rem;">
        Done
      </button>
    </div>
  </div>
</div>

<!-- DA LIVESTOCK DETAILS REVIEW MODAL -->
<div id="daLivestockDetailsModal" class="modal-overlay" onclick="if(event.target===this)closeDaLivestockDetailsModal()">
  <div class="modal-content" style="max-width:740px;">
    <div class="modal-header">
      <h3 id="daLvDetTagTitle"><i class="bi bi-tag-fill" style="color:#10b981;"></i> Livestock Inventory Review</h3>
      <button type="button" class="modal-close" onclick="closeDaLivestockDetailsModal()">&times;</button>
    </div>
    <div class="modal-body" id="daLvDetBody">
      <div style="text-align:center;padding:40px;color:var(--c-text-sub);">Loading...</div>
    </div>
    <div class="modal-actions" id="daLvDetActions">
      <button type="button" class="btn-cancel" onclick="closeDaLivestockDetailsModal()">Close</button>
    </div>
  </div>
</div>

<!-- DA VET REQUEST DETAILS REVIEW MODAL -->
<div id="daVetRequestDetailsModal" class="modal-overlay" onclick="if(event.target===this)closeDaVetRequestDetailsModal()">
  <div class="modal-content" style="max-width:740px;">
    <div class="modal-header">
      <h3 id="daVrDetCodeTitle"><i class="bi bi-bandaid-fill" style="color:#ef4444;"></i> Veterinary Request Review</h3>
      <button type="button" class="modal-close" onclick="closeDaVetRequestDetailsModal()">&times;</button>
    </div>
    <div class="modal-body" id="daVrDetBody">
      <div style="text-align:center;padding:40px;color:var(--c-text-sub);">Loading...</div>
    </div>
    <div class="modal-actions" id="daVrDetFooterActions">
      <button type="button" class="btn-cancel" onclick="closeDaVetRequestDetailsModal()">Close</button>
    </div>
  </div>
</div>

<!-- DA SLAUGHTER DETAILS REVIEW MODAL -->
<div id="daSlaughterDetailsModal" class="modal-overlay" onclick="if(event.target===this)closeDaSlaughterDetailsModal()">
  <div class="modal-content" style="max-width:740px;">
    <div class="modal-header">
      <h3 id="daSlDetTitle"><i class="bi bi-receipt-cutoff" style="color:#9d174d;"></i> Slaughter Declaration Review</h3>
      <button type="button" class="modal-close" onclick="closeDaSlaughterDetailsModal()">&times;</button>
    </div>
    <div class="modal-body" id="daSlDetBody">
      <div style="text-align:center;padding:40px;color:var(--c-text-sub);">Loading...</div>
    </div>
    <div class="modal-actions" id="daSlDetFooterActions">
      <button type="button" class="btn-cancel" onclick="closeDaSlaughterDetailsModal()">Close</button>
    </div>
  </div>
</div>

<!-- DA INVESTIGATE PUBLIC REPORT MODAL -->
<div id="daInvestigateReportModal" class="modal-overlay" onclick="if(event.target===this)closeDaInvestigateReportModal()">
  <div class="modal-content" style="max-width:760px;">
    <div class="modal-header">
      <h3 id="daRptDetTitle"><i class="bi bi-search" style="color:#7c3aed;"></i> Public Report Investigation Review</h3>
      <button type="button" class="modal-close" onclick="closeDaInvestigateReportModal()">&times;</button>
    </div>
    <div class="modal-body" id="daRptDetBody">
      <div style="text-align:center;padding:40px;color:var(--c-text-sub);">Loading...</div>
    </div>
    <div class="modal-actions" id="daRptDetFooterActions">
      <button type="button" class="btn-cancel" onclick="closeDaInvestigateReportModal()">Close</button>
    </div>
  </div>
</div>

<!-- DA INCIDENT DETAILS REVIEW MODAL -->
<div id="daIncidentDetailsModal" class="modal-overlay" onclick="if(event.target===this)closeDaIncidentDetailsModal()">
  <div class="modal-content" style="max-width:740px;">
    <div class="modal-header">
      <h3 id="daIncDetTitle"><i class="bi bi-exclamation-triangle-fill" style="color:#d97706;"></i> Incident Report Details</h3>
      <button type="button" class="modal-close" onclick="closeDaIncidentDetailsModal()">&times;</button>
    </div>
    <div class="modal-body" id="daIncDetBody">
      <div style="text-align:center;padding:40px;color:var(--c-text-sub);">Loading...</div>
    </div>
    <div class="modal-actions" id="daIncDetActions">
      <button type="button" class="btn-cancel" onclick="closeDaIncidentDetailsModal()">Close</button>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- 1. DA UNIFIED ASSISTANCE REQUEST REVIEW MODAL                   -->
<!-- ============================================================== -->
<div id="daAssistanceReviewModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(4px);z-index:9100;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:18px;width:95%;max-width:880px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);">
    <div style="padding:1.1rem 1.4rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:42px;height:42px;border-radius:12px;background:#ecfdf5;color:#065f46;display:flex;align-items:center;justify-content:center;font-size:1.3rem;">
          <i class="bi bi-file-earmark-medical-fill"></i>
        </div>
        <div>
          <h3 id="daArmTitle" style="font-size:1.15rem;font-weight:800;color:#0f172a;margin:0;">Assistance Request Details</h3>
          <span id="daArmSubtitle" style="font-size:.8rem;color:#64748b;">Loading request data...</span>
        </div>
      </div>
      <button type="button" style="background:#e2e8f0;border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#475569;" onclick="closeDaAssistanceReview()">&times;</button>
    </div>
    <div style="padding:1.25rem 1.4rem;overflow-y:auto;flex:1 1 auto;" id="daArmBody">
      <div style="text-align:center;padding:40px;color:#64748b;"><div class="spinner" style="margin:0 auto 10px;"></div>Loading assistance details...</div>
    </div>
    <div style="padding:.9rem 1.4rem;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <div id="daArmFooterLeft">
        <button type="button" class="btn btn-sm" id="btnDaArmDelete" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;font-weight:700;">
          <i class="bi bi-trash-fill"></i> Delete Request
        </button>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" class="an-btn" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;" id="btnDaArmStatus">
          <i class="bi bi-arrow-repeat"></i> Update Status
        </button>
        <button type="button" class="an-btn" style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;" id="btnDaArmVhc">
          <i class="bi bi-shield-plus"></i> Encode VHC
        </button>
        <button type="button" class="an-btn" style="background:#fdf2f8;color:#be185d;border:1px solid #fbcfe8;" id="btnDaArmCoi">
          <i class="bi bi-receipt-cutoff"></i> Encode COI
        </button>
        <button type="button" class="an-btn" style="background:#e2e8f0;color:#334155;" onclick="closeDaAssistanceReview()">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- 2. DA UPDATE ASSISTANCE STATUS MODAL                            -->
<!-- ============================================================== -->
<div id="daAssistanceStatusModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(4px);z-index:9200;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;width:90%;max-width:480px;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);overflow:hidden;">
    <div style="padding:1.1rem 1.25rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#eff6ff;">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:50%;background:#dbeafe;color:#1e40af;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
          <i class="bi bi-arrow-repeat"></i>
        </div>
        <h3 style="font-size:1rem;font-weight:700;color:#1e3a8a;margin:0;">Update Request Status</h3>
      </div>
      <button type="button" style="background:transparent;border:none;font-size:1.4rem;color:#64748b;cursor:pointer;" onclick="closeDaAssistanceStatusModal()">&times;</button>
    </div>
    <form id="daAssistanceStatusForm" onsubmit="event.preventDefault(); submitDaAssistanceStatus();">
      <input type="hidden" name="action" value="update_assistance_status">
      <input type="hidden" name="request_id" id="daStReqId" value="">
      <div style="padding:1.25rem;display:flex;flex-direction:column;gap:1rem;">
        <div>
          <label style="font-size:.82rem;font-weight:700;color:#334155;display:block;margin-bottom:4px;">Official Processing Status *</label>
          <select name="status" id="daStSelectStatus" class="form-input" style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.88rem;font-weight:600;" required>
            <option value="Request Submitted">Request Submitted</option>
            <option value="Pending Review">Pending Review</option>
            <option value="Under Review">Under Review</option>
            <option value="For Inspection">For Inspection</option>
            <option value="Approved">Approved</option>
            <option value="Certificate Processing">Certificate Processing</option>
            <option value="Certificate Issued">Certificate Issued</option>
            <option value="Completed">Completed</option>
            <option value="Rejected">Rejected</option>
          </select>
        </div>
        <div>
          <label style="font-size:.82rem;font-weight:700;color:#334155;display:block;margin-bottom:4px;">Official Remarks / Notes to Farmer</label>
          <textarea name="official_remarks" id="daStRemarks" rows="3" class="form-input" placeholder="e.g. Approved for veterinary inspection. Assigned team visiting farm on Friday..." style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;"></textarea>
          <small style="font-size:.72rem;color:#64748b;display:block;margin-top:4px;">
            Farmer will receive a notification in their portal with these remarks.
          </small>
        </div>
      </div>
      <div style="padding:.875rem 1.25rem;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="an-btn" style="background:#e2e8f0;color:#334155;" onclick="closeDaAssistanceStatusModal()">Cancel</button>
        <button type="submit" class="an-btn an-btn-primary" id="btnDaStSubmit"><i class="bi bi-check2-circle"></i> Save Status</button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================== -->
<!-- 3. DA ENCODE VETERINARY HEALTH CERTIFICATE (VHC) MODAL          -->
<!-- ============================================================== -->
<div id="daEncodeVhcModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(4px);z-index:9250;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:18px;width:95%;max-width:840px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);">
    <div style="padding:1.1rem 1.4rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#ecfdf5;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:40px;height:40px;border-radius:12px;background:#d1fae5;color:#065f46;display:flex;align-items:center;justify-content:center;font-size:1.3rem;">
          <i class="bi bi-shield-plus"></i>
        </div>
        <div>
          <h3 style="font-size:1.15rem;font-weight:800;color:#065f46;margin:0;">Encode Veterinary Health Certificate (VHC)</h3>
          <span style="font-size:.78rem;color:#047857;">Official Philippine DA Biosecurity &amp; Disease-Free Movement Clearance</span>
        </div>
      </div>
      <button type="button" style="background:#e2e8f0;border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#475569;" onclick="closeDaEncodeVhcModal()">&times;</button>
    </div>
    <form id="daEncodeVhcForm" onsubmit="event.preventDefault(); submitDaVhcCertificate(event);" style="display:flex;flex-direction:column;flex:1 1 auto;overflow-y:auto;">
      <input type="hidden" name="action" value="encode_vhc_certificate">
      <input type="hidden" name="request_id" id="vhcReqId" value="">
      
      <div style="padding:1.25rem 1.4rem;display:flex;flex-direction:column;gap:1rem;">
        
        <!-- Top Control Info -->
        <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;padding:12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">VHC Control Number *</label>
            <input type="text" name="control_number" id="vhcControlNo" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-family:monospace;font-weight:800;font-size:.9rem;color:#065f46;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Certificate Date *</label>
            <input type="date" name="issue_date" id="vhcIssueDate" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Certificate Expiration Date *</label>
            <input type="date" name="expiry_date" id="vhcExpiryDate" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
        </div>

        <!-- Owner & Shipment Details -->
        <div style="display:grid;grid-template-columns:1fr 1.3fr .8fr;gap:10px;">
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Owner Name *</label>
            <input type="text" name="owner_name" id="vhcOwnerName" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Owner Address *</label>
            <input type="text" name="owner_address" id="vhcOwnerAddr" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Date of Shipment *</label>
            <input type="date" name="shipment_date" id="vhcShipmentDate" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
        </div>

        <!-- Assigned Veterinarian Details -->
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;">
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#166534;display:block;margin-bottom:3px;">Assigned LGU Veterinarian *</label>
            <input type="text" name="vet_name" id="vhcVetName" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #86efac;border-radius:8px;font-size:.86rem;font-weight:700;color:#14532d;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#166534;display:block;margin-bottom:3px;">Position / Title *</label>
            <input type="text" name="vet_position" id="vhcVetPosition" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #86efac;border-radius:8px;font-size:.84rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#166534;display:block;margin-bottom:3px;">Place of Issue *</label>
            <input type="text" name="place_of_issue" id="vhcPlaceOfIssue" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #86efac;border-radius:8px;font-size:.84rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#166534;display:block;margin-bottom:3px;">PRC License No.</label>
            <input type="text" name="prc_license_no" id="vhcPrcNo" class="form-input" placeholder="e.g. 0012345" style="width:100%;padding:.55rem .75rem;border:1px solid #86efac;border-radius:8px;font-size:.84rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#166534;display:block;margin-bottom:3px;">PRC Validity</label>
            <input type="text" name="prc_validity" id="vhcPrcValidity" class="form-input" placeholder="e.g. Dec 2028" style="width:100%;padding:.55rem .75rem;border:1px solid #86efac;border-radius:8px;font-size:.84rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#166534;display:block;margin-bottom:3px;">Vet Contact No.</label>
            <input type="text" name="vet_contact_no" id="vhcVetContact" class="form-input" placeholder="09XX-XXX-XXXX" style="width:100%;padding:.55rem .75rem;border:1px solid #86efac;border-radius:8px;font-size:.84rem;">
          </div>
        </div>

        <!-- Verified Livestock Particulars -->
        <div>
          <label style="font-size:.82rem;font-weight:800;color:#0f172a;display:block;margin-bottom:6px;">
            <i class="bi bi-check2-circle" style="color:#059669;"></i> Verified Livestock Particulars (Subject to actual inspection)
          </label>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;">
            <div>
              <label style="font-size:.75rem;font-weight:700;color:#475569;">Verified Species *</label>
              <input type="text" name="verified_species" id="vhcSpecies" class="form-input" required style="width:100%;padding:.55rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
            </div>
            <div>
              <label style="font-size:.75rem;font-weight:700;color:#475569;">Verified Heads *</label>
              <input type="number" name="verified_heads" id="vhcHeads" class="form-input" min="1" required style="width:100%;padding:.55rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;font-weight:700;">
            </div>
            <div>
              <label style="font-size:.75rem;font-weight:700;color:#475569;">Verified Sex *</label>
              <input type="text" name="verified_sex" id="vhcSex" class="form-input" required style="width:100%;padding:.55rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
            </div>
            <div>
              <label style="font-size:.75rem;font-weight:700;color:#475569;">Verified Age *</label>
              <input type="text" name="verified_age" id="vhcAge" class="form-input" required style="width:100%;padding:.55rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
            </div>
            <div>
              <label style="font-size:.75rem;font-weight:700;color:#475569;">Purpose *</label>
              <input type="text" name="purpose" id="vhcPurpose" class="form-input" required style="width:100%;padding:.55rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
            </div>
          </div>
        </div>

        <!-- Origin and Destination -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Verified Origin *</label>
            <input type="text" name="origin" id="vhcOrigin" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Verified Destination *</label>
            <input type="text" name="destination" id="vhcDest" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
        </div>

        <!-- Treatment / Medication History Rows (Auto-populated into VHC table) -->
        <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;padding:10px;">
          <label style="font-size:.78rem;font-weight:800;color:#334155;display:block;margin-bottom:6px;">Treatment / Medication History (leave blank for N/A)</label>
          <div style="display:grid;grid-template-columns:1fr 1.4fr 1fr 1fr;gap:6px;margin-bottom:6px;">
            <input type="text" name="tr_date_1" id="vhcTrDate1" placeholder="Date (or N/A)" style="padding:.42rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.8rem;">
            <input type="text" name="tr_med_1" id="vhcTrMed1" placeholder="Medicine Administered" style="padding:.42rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.8rem;">
            <input type="text" name="tr_route_1" id="vhcTrRoute1" placeholder="Route" style="padding:.42rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.8rem;">
            <input type="text" name="tr_with_1" id="vhcTrWith1" placeholder="Withdrawal Period" style="padding:.42rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.8rem;">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1.4fr 1fr 1fr;gap:6px;">
            <input type="text" name="tr_date_2" id="vhcTrDate2" placeholder="Date 2 (optional)" style="padding:.42rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.8rem;">
            <input type="text" name="tr_med_2" id="vhcTrMed2" placeholder="Medicine 2" style="padding:.42rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.8rem;">
            <input type="text" name="tr_route_2" id="vhcTrRoute2" placeholder="Route 2" style="padding:.42rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.8rem;">
            <input type="text" name="tr_with_2" id="vhcTrWith2" placeholder="Withdrawal 2" style="padding:.42rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.8rem;">
          </div>
          <input type="hidden" name="treatment_verification" id="vhcTreatVer" value="Verified free from signs of dangerous communicable diseases.">
        </div>

        <div style="background:#eff6ff;padding:10px 14px;border-radius:8px;border:1px solid #bfdbfe;font-size:.75rem;color:#1e40af;">
          <i class="bi bi-info-circle-fill"></i> Upon encoding, the request status is automatically updated to <b>Certificate Issued</b>. The farmer can instantly preview and print their official VHC with authentic Republic of the Philippines headers.
        </div>

      </div>
      <div style="padding:.9rem 1.4rem;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="an-btn" style="background:#e2e8f0;color:#334155;" onclick="closeDaEncodeVhcModal()">Cancel</button>
        <button type="submit" class="an-btn an-btn-primary" id="btnSubmitVhc"><i class="bi bi-send-check-fill"></i> Submit &amp; Send VHC to Farmer Portal</button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================== -->
<!-- 4. DA ENCODE CERTIFICATE OF INSPECTION (COI) MODAL              -->
<!-- ============================================================== -->
<div id="daEncodeCoiModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(4px);z-index:9250;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:18px;width:95%;max-width:840px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);">
    <div style="padding:1.1rem 1.4rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#fdf2f8;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:40px;height:40px;border-radius:12px;background:#fce7f3;color:#be185d;display:flex;align-items:center;justify-content:center;font-size:1.3rem;">
          <i class="bi bi-receipt-cutoff"></i>
        </div>
        <div>
          <h3 style="font-size:1.15rem;font-weight:800;color:#9d174d;margin:0;">Encode Certificate of Inspection (COI)</h3>
          <span style="font-size:.78rem;color:#be185d;">DA-NMIS Official Live Animal Transport &amp; Meat Safety Inspection</span>
        </div>
      </div>
      <button type="button" style="background:#e2e8f0;border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#475569;" onclick="closeDaEncodeCoiModal()">&times;</button>
    </div>
    <form id="daEncodeCoiForm" onsubmit="event.preventDefault(); submitDaCoiCertificate(event);" style="display:flex;flex-direction:column;flex:1 1 auto;overflow-y:auto;">
      <input type="hidden" name="action" value="encode_coi_certificate">
      <input type="hidden" name="request_id" id="coiReqId" value="">
      
      <div style="padding:1.25rem 1.4rem;display:flex;flex-direction:column;gap:1rem;">
        
        <!-- Top Control Info -->
        <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;padding:12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;">
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">COI Control Number *</label>
            <input type="text" name="control_number" id="coiControlNo" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-family:monospace;font-weight:800;font-size:.88rem;color:#9d174d;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Date Inspected *</label>
            <input type="date" name="inspection_date" id="coiInspectDate" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Date Issued *</label>
            <input type="date" name="issue_date" id="coiIssueDate" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Valid Until *</label>
            <input type="date" name="expiry_date" id="coiExpiryDate" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem;">
          </div>
        </div>

        <!-- Official Inspector & Signatory Details -->
        <div style="background:#fff1f2;border:1px solid #fecdd3;border-radius:12px;padding:12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#9f1239;display:block;margin-bottom:3px;">Veterinarian Name *</label>
            <input type="text" name="vet_name" id="coiVetName" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #fda4af;border-radius:8px;font-size:.86rem;font-weight:700;color:#881337;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#9f1239;display:block;margin-bottom:3px;">Position / Title *</label>
            <input type="text" name="vet_position" id="coiVetPosition" class="form-input" value="Veterinarian I" required style="width:100%;padding:.55rem .75rem;border:1px solid #fda4af;border-radius:8px;font-size:.84rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#9f1239;display:block;margin-bottom:3px;">NOTED: Municipal Agriculturist</label>
            <input type="text" name="municipal_agriculturist" id="coiMuniAgri" class="form-input" placeholder="Municipal Agriculturist Name" style="width:100%;padding:.55rem .75rem;border:1px solid #fda4af;border-radius:8px;font-size:.84rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#9f1239;display:block;margin-bottom:3px;">Place of Issue *</label>
            <input type="text" name="place_of_issue" id="coiPlaceOfIssue" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #fda4af;border-radius:8px;font-size:.84rem;">
          </div>
        </div>

        <!-- Verified Heads, Value & Purpose -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;">
          <div>
            <label style="font-size:.75rem;font-weight:700;color:#475569;">Verified Species *</label>
            <input type="text" name="verified_species" id="coiSpecies" class="form-input" required style="width:100%;padding:.55rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
          <div>
            <label style="font-size:.75rem;font-weight:700;color:#475569;">Verified Heads *</label>
            <input type="number" name="verified_heads" id="coiHeads" class="form-input" min="1" required style="width:100%;padding:.55rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;font-weight:700;">
          </div>
          <div>
            <label style="font-size:.75rem;font-weight:700;color:#475569;">Verified Value (PHP) *</label>
            <input type="number" step="0.01" name="value" id="coiValue" class="form-input" required style="width:100%;padding:.55rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;font-weight:700;">
          </div>
          <div>
            <label style="font-size:.75rem;font-weight:700;color:#475569;">Purpose *</label>
            <select name="purpose" id="coiPurpose" class="form-input" style="width:100%;padding:.55rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
              <option value="Slaughter">Slaughter</option>
              <option value="Commercial">Commercial</option>
              <option value="Personal">Personal</option>
            </select>
          </div>
        </div>

        <!-- Origin, Destination & Transport -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Verified Origin *</label>
            <input type="text" name="origin" id="coiOrigin" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Verified Destination *</label>
            <input type="text" name="destination" id="coiDest" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Means of Transport *</label>
            <input type="text" name="transport_means" id="coiTransportMeans" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Vehicle Plate Number *</label>
            <input type="text" name="plate_number" id="coiPlateNumber" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;font-weight:700;font-family:monospace;">
          </div>
        </div>

        <!-- Shipper Details -->
        <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:12px;">
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Shipper / Consignor Name *</label>
            <input type="text" name="shipper" id="coiShipper" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Shipper Complete Address *</label>
            <input type="text" name="shipper_address" id="coiShipperAddr" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
        </div>

        <!-- Official Receipt (O.R.) & Fees -->
        <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;padding:12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Paid under O.R. No. *</label>
            <input type="text" name="or_number" id="coiOrNumber" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;font-family:monospace;font-weight:700;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Amount Paid (PHP) *</label>
            <input type="number" step="0.01" name="amount_paid" id="coiAmountPaid" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;font-weight:700;">
          </div>
          <div>
            <label style="font-size:.78rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Payment Date *</label>
            <input type="date" name="payment_date" id="coiPaymentDate" class="form-input" required style="width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;">
          </div>
        </div>

      </div>
      <div style="padding:.9rem 1.4rem;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="an-btn" style="background:#e2e8f0;color:#334155;" onclick="closeDaEncodeCoiModal()">Cancel</button>
        <button type="submit" class="an-btn" style="background:#db2777;color:#fff;" id="btnSubmitCoi"><i class="bi bi-send-check-fill"></i> Submit &amp; Send COI to Farmer Portal</button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================== -->
<!-- 4B. DA INDEMNITY CLAIMS REQUIREMENTS & CERTIFICATE MODAL       -->
<!-- ============================================================== -->
<div id="daIndemnityCertModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.72);backdrop-filter:blur(4px);z-index:9260;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:18px;width:95%;max-width:860px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);">
    <div style="padding:1.05rem 1.4rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#eef2ff;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:40px;height:40px;border-radius:12px;background:#e0e7ff;color:#4338ca;display:flex;align-items:center;justify-content:center;font-size:1.3rem;">
          <i class="bi bi-file-earmark-check-fill"></i>
        </div>
        <div>
          <h3 style="font-size:1.1rem;font-weight:800;color:#312e81;margin:0;">Indemnity Claims Verification &amp; Certificate Issuance</h3>
          <span style="font-size:.76rem;color:#4338ca;">Verify complete farmer requirements before issuing Official Indemnity Claim Certificate</span>
        </div>
      </div>
      <button type="button" style="background:#e2e8f0;border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:1.2rem;color:#475569;" onclick="closeDaIndemnityModal()">&times;</button>
    </div>
    <form id="daIndemnityCertForm" onsubmit="event.preventDefault(); submitDaIndemnityCert(event);" style="display:flex;flex-direction:column;flex:1 1 auto;overflow-y:auto;">
      <input type="hidden" name="action" value="issue_indemnity_certificate">
      <input type="hidden" name="insurance_id" id="indInsId" value="">
      <input type="hidden" name="farmer_id" id="indFarmerId" value="">
      <div style="padding:1.2rem 1.4rem;display:flex;flex-direction:column;gap:.95rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;padding:12px;">
          <div>
            <label style="font-size:.76rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Certificate Control No. *</label>
            <input type="text" name="control_number" id="indControlNo" required style="width:100%;padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-family:monospace;font-weight:800;color:#4338ca;">
          </div>
          <div>
            <label style="font-size:.76rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Date Issued *</label>
            <input type="date" name="issue_date" id="indIssueDate" required style="width:100%;padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem;">
          </div>
          <div>
            <label style="font-size:.76rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Estimated Loss / Value (PHP)</label>
            <input type="number" step="0.01" name="estimated_loss" id="indEstLoss" value="0.00" style="width:100%;padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem;font-weight:700;">
          </div>
        </div>

        <!-- 1. Barangay Certification -->
        <div style="border:1px solid #c7d2fe;border-radius:12px;padding:12px;background:#f5f7ff;">
          <label style="display:flex;align-items:center;gap:8px;font-weight:800;font-size:.84rem;color:#312e81;margin-bottom:8px;cursor:pointer;">
            <input type="checkbox" name="req_brgy_cert" id="indReqBrgyCert" value="1" required style="width:16px;height:16px;">
            1. Barangay Certification Submitted &amp; Verified (Mga Nilalaman):
          </label>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
            <div>
              <label style="font-size:.74rem;font-weight:700;color:#475569;">Name of Farmer *</label>
              <input type="text" name="farmer_name" id="indFarmerName" required style="width:100%;padding:.48rem .65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem;">
            </div>
            <div>
              <label style="font-size:.74rem;font-weight:700;color:#475569;">Barangay *</label>
              <input type="text" name="farmer_barangay" id="indFarmerBrgy" required style="width:100%;padding:.48rem .65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem;">
            </div>
            <div>
              <label style="font-size:.74rem;font-weight:700;color:#475569;">Kind of Animal &amp; Gender *</label>
              <input type="text" name="animal_kind_gender" id="indAnimalKind" placeholder="e.g. Swine - Female" required style="width:100%;padding:.48rem .65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem;">
            </div>
            <div>
              <label style="font-size:.74rem;font-weight:700;color:#475569;">Date of Loss *</label>
              <input type="date" name="date_of_loss" id="indDateLoss" required style="width:100%;padding:.48rem .65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem;">
            </div>
            <div style="grid-column:1 / -1;">
              <label style="font-size:.74rem;font-weight:700;color:#475569;">Cause of Death *</label>
              <input type="text" name="cause_of_death" id="indCauseDeath" placeholder="e.g. Acute Illness / Disease / Accident" required style="width:100%;padding:.48rem .65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem;">
            </div>
          </div>
        </div>

        <!-- 2. Two Unrelated Witnesses in Same Barangay -->
        <div style="border:1px solid #bbf7d0;border-radius:12px;padding:12px;background:#f0fdf4;">
          <div style="font-weight:800;font-size:.84rem;color:#14532d;margin-bottom:8px;">2. Two (2) Witnesses (Nakatira sa parehong Barangay na hindi kaapelyido)</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="background:#fff;border:1px solid #dcfce7;border-radius:10px;padding:10px;">
              <strong style="font-size:.78rem;color:#166534;display:block;margin-bottom:6px;">Witness 1</strong>
              <input type="text" name="witness1_name" id="indW1Name" placeholder="Witness 1 Full Name *" required style="width:100%;padding:.45rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.82rem;margin-bottom:6px;">
              <input type="text" name="witness1_cedula" id="indW1Cedula" placeholder="Cedula (CTC) No. *" required style="width:100%;padding:.45rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.82rem;margin-bottom:6px;">
              <label style="display:flex;align-items:center;gap:6px;font-size:.76rem;margin-bottom:4px;"><input type="checkbox" name="witness1_same_brgy_unrelated" value="1" required> Same Barangay &amp; Not Related (Hindi kaapelyido)</label>
              <label style="display:flex;align-items:center;gap:6px;font-size:.76rem;margin-bottom:4px;"><input type="checkbox" name="witness1_affidavit" value="1" required> Affidavit confirming animal death</label>
              <label style="display:flex;align-items:center;gap:6px;font-size:.76rem;"><input type="checkbox" name="witness1_valid_id" value="1" required> Valid ID &amp; Witness present in processing</label>
            </div>
            <div style="background:#fff;border:1px solid #dcfce7;border-radius:10px;padding:10px;">
              <strong style="font-size:.78rem;color:#166534;display:block;margin-bottom:6px;">Witness 2</strong>
              <input type="text" name="witness2_name" id="indW2Name" placeholder="Witness 2 Full Name *" required style="width:100%;padding:.45rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.82rem;margin-bottom:6px;">
              <input type="text" name="witness2_cedula" id="indW2Cedula" placeholder="Cedula (CTC) No. *" required style="width:100%;padding:.45rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.82rem;margin-bottom:6px;">
              <label style="display:flex;align-items:center;gap:6px;font-size:.76rem;margin-bottom:4px;"><input type="checkbox" name="witness2_same_brgy_unrelated" value="1" required> Same Barangay &amp; Not Related (Hindi kaapelyido)</label>
              <label style="display:flex;align-items:center;gap:6px;font-size:.76rem;margin-bottom:4px;"><input type="checkbox" name="witness2_affidavit" value="1" required> Affidavit confirming animal death</label>
              <label style="display:flex;align-items:center;gap:6px;font-size:.76rem;"><input type="checkbox" name="witness2_valid_id" value="1" required> Valid ID &amp; Witness present in processing</label>
            </div>
          </div>
        </div>

        <!-- 3. Owner Requirements -->
        <div style="border:1px solid #fde68a;border-radius:12px;padding:12px;background:#fffbeb;">
          <div style="font-weight:800;font-size:.84rem;color:#92400e;margin-bottom:8px;">3. Owner (May-ari) Requirements</div>
          <label style="display:flex;align-items:center;gap:8px;font-size:.8rem;color:#78350f;margin-bottom:6px;cursor:pointer;">
            <input type="checkbox" name="req_owner_affidavit" value="1" required style="width:15px;height:15px;">
            Owner's Affidavit submitted (narrative of what happened when the dead animal was found)
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-size:.8rem;color:#78350f;margin-bottom:8px;cursor:pointer;">
            <input type="checkbox" name="req_dead_animal_photos" value="1" required style="width:15px;height:15px;">
            Pictures of the dead animal submitted &amp; verified
          </label>
          <input type="text" name="photo_receiver_note" id="indPhotoNote" value="Verified photos received by DA Staff (Anaznerol Lei / Official Portal)" style="width:100%;padding:.45rem .65rem;border:1px solid #fcd34d;border-radius:8px;font-size:.8rem;">
        </div>

        <!-- Signatories -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div>
            <label style="font-size:.76rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">Verifying Veterinarian / Officer *</label>
            <input type="text" name="vet_name" id="indVetName" required style="width:100%;padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem;">
          </div>
          <div>
            <label style="font-size:.76rem;font-weight:700;color:#334155;display:block;margin-bottom:3px;">NOTED: Municipal Agriculturist</label>
            <input type="text" name="municipal_agriculturist" id="indMuniAgri" placeholder="Municipal Agriculturist Name" style="width:100%;padding:.5rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.84rem;">
          </div>
        </div>
      </div>
      <div style="padding:.9rem 1.4rem;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="an-btn" style="background:#e2e8f0;color:#334155;" onclick="closeDaIndemnityModal()">Cancel</button>
        <button type="submit" class="an-btn an-btn-primary" id="btnSubmitIndemnity"><i class="bi bi-check2-circle"></i> Issue Indemnity Claim Certificate</button>
      </div>
    </form>
  </div>
</div>

<!-- 5. DA OFFICIAL CERTIFICATE VIEW / PRINT / PDF MODAL             -->
<!-- ============================================================== -->
<div id="daAssistanceCertViewModal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(4px);z-index:9300;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;width:95%;max-width:8.8in;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;">
      <div>
        <h3 id="daCertViewTitle" style="font-size:1.1rem;font-weight:700;color:#0f172a;margin:0;">Official Certificate View</h3>
        <span id="daCertViewSubtitle" style="font-size:.78rem;color:#64748b;">Republic of the Philippines &bull; Department of Agriculture</span>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <button type="button" class="btn btn-sm btn-primary" id="btnSendCertToFarmer" onclick="sendDaCertToFarmerPortal()" style="background:#059669;border-color:#059669;color:#fff;font-weight:700;"><i class="bi bi-send-check-fill"></i> Submit to Farmer Portal</button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="printDaOfficialCertDoc()"><i class="bi bi-printer-fill"></i> Print</button>
        <button type="button" class="btn btn-sm btn-secondary" id="btnDaCertDownloadPdf" onclick="downloadDaOfficialCertPdf()"><i class="bi bi-file-earmark-pdf-fill"></i> Download PDF</button>
        <button type="button" style="background:#e2e8f0;border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#475569;" onclick="closeDaAssistanceCertView()">&times;</button>
      </div>
    </div>
    <div style="padding:1.5rem;overflow-y:auto;flex:1 1 auto;background:#f1f5f9;" id="daCertViewWrapper">
      <div id="daCertViewContent"></div>
    </div>
  </div>
</div>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php if (!empty($googleMapsApiKey)): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($googleMapsApiKey) ?>&callback=initGoogleOfficialMap" async defer></script>
<?php endif; ?>
<script>
const farmsData             = <?= json_encode($map_farms_and_registrations) ?>;
const incidentsData         = <?= json_encode($all_incidents) ?>;
const publicReportsData     = <?= json_encode($public_reports) ?>;
const allFarmersData        = <?= json_encode($all_farmers) ?>;
const allLivestockData      = <?= json_encode($all_livestock) ?>;
const allVetRequestsData    = <?= json_encode($all_vet_requests) ?>;
const allSlaughterData      = <?= json_encode($all_slaughter) ?>;
const allHealthRecordsData  = <?= json_encode($all_health_records) ?>;
const allPublicReportsData  = <?= json_encode($public_reports) ?>;
const farmRegSubmissionsData = <?= json_encode($farmRegSubmissions) ?>;
const rsbsaSubmissionsData  = <?= json_encode($rsbsaSubmissions) ?>;
const pcicSubmissionsData   = <?= json_encode($pcicSubmissions) ?>;
const APP_BASE              = <?= json_encode(app_base()) ?>;
const CURRENT_USER_ID   = <?= json_encode((int)$user_id) ?>;
const CURRENT_NAME      = <?= json_encode(trim(($user_profile['firstName'] ?? '') . ' ' . ($user_profile['lastName'] ?? ''))) ?>;
const daAssistanceRequests = <?= json_encode($all_assistance_requests, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const daAssistanceCerts = <?= json_encode($all_assistance_certs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const daIndemnityCerts = <?= json_encode($all_indemnity_certs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const daLguVets = <?= json_encode($lguVets) ?>;
const daCurrentLguVet = <?= json_encode($currentLguVet) ?>;
const daOfficerMuni = <?= json_encode($officerMuni) ?>;

function closeSidebar() {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('overlay');
  if (sb) sb.classList.remove('open', 'hover-open');
  if (ov) ov.classList.remove('open');
  document.body.classList.remove('sidebar-open');
}

function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('overlay');
  if (!sb) return;
  if (window.innerWidth < 1024) {
    const isOpen = sb.classList.toggle('open');
    sb.classList.remove('hover-open');
    if (ov) {
      if (isOpen) ov.classList.add('open');
      else ov.classList.remove('open');
    }
    document.body.classList.toggle('sidebar-open', isOpen);
  } else {
    if (document.body.classList.contains('sidebar-autohide')) {
      const isOpen = sb.classList.toggle('hover-open');
      if (ov) {
        if (isOpen) ov.classList.add('open');
        else ov.classList.remove('open');
      }
    } else {
      if (typeof applyDaAutoHide === 'function') {
        applyDaAutoHide(!isDaAutoHide);
      } else {
        sb.classList.toggle('hover-open');
      }
    }
  }
}

const CSRF_TOKEN = <?= json_encode($csrf_token ?? '') ?>;

function toggleDaDropdown(id) {
  const sub = document.getElementById(id);
  const btn = document.getElementById('btn-' + id);
  if (!sub || !btn) return;
  const isOpen = sub.classList.contains('open');
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

function navTo(id, el) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  const targetSec = document.getElementById('sec-' + id);
  if (targetSec) targetSec.classList.add('active');
  document.querySelectorAll('.nav-item, .da-subitem').forEach(n => n.classList.remove('active'));
  if (el) {
    el.classList.add('active');
    const span = el.querySelector('span');
    if (span) document.getElementById('page-title').innerText = span.innerText;
  }

  // Auto-open Service Request dropdown if nav target is inside it
  if (['vet-requests', 'slaughter'].includes(id)) {
    const sub = document.getElementById('da-drop-services');
    const btn = document.getElementById('btn-da-drop-services');
    if (sub && !sub.classList.contains('open')) {
      sub.classList.add('open');
      btn?.classList.add('open');
      btn?.setAttribute('aria-expanded', 'true');
    }
    const matchingSub = document.querySelector(`.da-subitem[onclick*="'${id}'"]`);
    if (matchingSub) {
      document.querySelectorAll('.da-subitem').forEach(n => n.classList.remove('active'));
      matchingSub.classList.add('active');
    }
  }

  // Auto-open Document Verification dropdown if nav target is inside it
  if (['farms', 'insurance', 'documents', 'form-review'].includes(id)) {
    const sub = document.getElementById('da-drop-doc');
    const btn = document.getElementById('btn-da-drop-doc');
    if (sub && !sub.classList.contains('open')) {
      sub.classList.add('open');
      btn?.classList.add('open');
      btn?.setAttribute('aria-expanded', 'true');
    }
    const matchingSub = document.querySelector(`.da-subitem[onclick*="'${id}'"]`);
    if (matchingSub) {
      document.querySelectorAll('.da-subitem').forEach(n => n.classList.remove('active'));
      matchingSub.classList.add('active');
    }
  }

  closeSidebar();
  if (id === 'reports') {
    animateBars();
    ['livestockTrendChart', 'animalDonutChart'].forEach(cid => { const c = (typeof Chart !== 'undefined') ? Chart.getChart(cid) : null; if (c) c.resize(); });
  }
  if (id === 'map') {
    const mapFrame = document.querySelector('.map-frame-view');
    if (mapFrame && mapFrame.contentWindow) {
      try { mapFrame.contentWindow.postMessage({ action: 'resize' }, '*'); } catch (e) {}
      setTimeout(() => { try { mapFrame.contentWindow.postMessage({ action: 'resize' }, '*'); } catch (e) {} }, 150);
      setTimeout(() => { try { mapFrame.contentWindow.postMessage({ action: 'resize' }, '*'); } catch (e) {} }, 350);
    }
  }
  
}

// -- OFFICIAL AUTO -- HIDE / FULL VIEW ENGINE -------------------------
const daSidebar     = document.getElementById('sidebar');
const daHoverZone   = document.getElementById('daHoverZone');
const daEdgeTrigger = document.getElementById('daEdgeTrigger');
const daPinBtn      = document.getElementById('daPinBtn');
const daOverlay     = document.getElementById('overlay');

let isDaAutoHide = localStorage.getItem('agritrace_da_autohide') !== 'false';

function applyDaAutoHide(enabled) {
  isDaAutoHide = enabled;
  localStorage.setItem('agritrace_da_autohide', enabled ? 'true' : 'false');
  if (enabled) {
    document.body.classList.add('sidebar-autohide');
    if (daPinBtn) daPinBtn.innerHTML = '<i class="bi bi-pin-angle" title="Click to Pin Sidebar Open"></i>';
  } else {
    document.body.classList.remove('sidebar-autohide');
    if (daSidebar) daSidebar.classList.remove('hover-open');
    if (daPinBtn) daPinBtn.innerHTML = '<i class="bi bi-pin-fill" title="Click to Auto-Hide for Full View"></i>';
  }
  setTimeout(() => {
    window.dispatchEvent(new Event('resize'));
    ['livestockTrendChart', 'animalDonutChart'].forEach(cid => { 
      const c = (typeof Chart !== 'undefined') ? Chart.getChart(cid) : null; 
      if (c) c.resize(); 
    });
    const mapFrame = document.querySelector('.map-frame-view');
    if (mapFrame && mapFrame.contentWindow) {
      try { mapFrame.contentWindow.postMessage({ action: 'resize' }, '*'); } catch (e) {}
    }
  }, 300);
}

applyDaAutoHide(isDaAutoHide);

daPinBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  applyDaAutoHide(!isDaAutoHide);
});

let daHoverTimer = null;
function openDaHover() {
  if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth <= 1024) return;
  clearTimeout(daHoverTimer);
  daSidebar?.classList.add('hover-open');
}
function closeDaHover() {
  if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth <= 1024) return;
  clearTimeout(daHoverTimer);
  daHoverTimer = setTimeout(() => {
    daSidebar?.classList.remove('hover-open');
  }, 300);
}

daHoverZone?.addEventListener('mouseenter', openDaHover);
daEdgeTrigger?.addEventListener('mouseenter', openDaHover);
daEdgeTrigger?.addEventListener('click', (e) => {
  e.stopPropagation();
  daSidebar?.classList.toggle('hover-open');
});

daSidebar?.addEventListener('mouseenter', () => clearTimeout(daHoverTimer));
daSidebar?.addEventListener('mouseleave', closeDaHover);

document.addEventListener('mousemove', (e) => {
  if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth <= 1024) return;
  if (e.clientX <= 20) {
    openDaHover();
  } else if (e.clientX > 320 && daSidebar?.classList.contains('hover-open')) {
    closeDaHover();
  }
});

daOverlay?.addEventListener('click', closeSidebar);

document.addEventListener('keydown', (e) => {
  if ((e.ctrlKey && e.key.toLowerCase() === 'b') || (e.key === '[' && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName))) {
    e.preventDefault();
    applyDaAutoHide(!isDaAutoHide);
  }
});

function activateSection(id) {
  const navEl = document.querySelector(`.nav-item[onclick*="'${id}'"], .da-subitem[onclick*="'${id}'"]`);
  navTo(id, navEl);
}
window.activateSection = activateSection;

// Listen for cross-frame messages (e.g. Map "Dashboard" return button)
window.addEventListener('message', (event) => {
  if (event.data && event.data.action === 'nav' && event.data.section) {
    activateSection(event.data.section);
  }
});

function searchFarms(q) {
  document.querySelectorAll('#farmsTable tbody tr').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
  });
}

async function approveFarm(farmId) {
  if (!confirm('Approve this farm?')) return;
  showLoading(true);
  try {
    const res = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=approve_farm&farm_id=${farmId}` });
    const r = await res.json();
    if (r.success) { updateFarmRow(farmId, 'Approved'); showToast('Farm approved!', 'success'); }
    else showToast(r.message, 'error');
  } catch { showToast('Network error', 'error'); }
  finally { showLoading(false); }
}

async function rejectFarm(farmId) {
  if (!confirm('Reject this farm?')) return;
  showLoading(true);
  try {
    const res = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=reject_farm&farm_id=${farmId}` });
    const r = await res.json();
    if (r.success) { updateFarmRow(farmId, 'Rejected'); showToast('Farm rejected.', 'success'); }
    else showToast(r.message, 'error');
  } catch { showToast('Network error', 'error'); }
  finally { showLoading(false); }
}

function updateFarmRow(id, status) {
  const row = document.querySelector(`#farmsTable tr[data-farm-id="${id}"]`);
  if (!row) return;
  const statusCell = row.querySelector('.farm-status-cell') || row.cells[4];
  const actionCell = row.querySelector('.farm-action-cell') || row.cells[6];
  if (statusCell) statusCell.innerHTML = `<span class="status-badge status-${status.toLowerCase()}">${status}</span>`;
  if (actionCell) actionCell.innerHTML = `<span style="color:var(--c-text-sub);font-size:0.82rem;font-weight:600;">${status === 'Approved' ? '\u2713 Approved' : '\u2717 Rejected'}</span>`;
  if (typeof farmsFullData !== 'undefined') {
    const f = farmsFullData.find(x => parseInt(x.id) === parseInt(id));
    if (f) f.status = status;
  }
}

async function updateIncidentPriority(incidentId, priority, selectEl) {
  showLoading(true);
  try {
    const res = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=update_incident_priority&incident_id=${incidentId}&priority=${encodeURIComponent(priority)}` });
    const r = await res.json();
    if (r.success) { selectEl.setAttribute('data-orig', priority); showToast(`Priority updated to ${priority}`, 'success'); }
    else { selectEl.value = selectEl.getAttribute('data-orig'); showToast(r.message, 'error'); }
  } catch { selectEl.value = selectEl.getAttribute('data-orig'); showToast('Network error', 'error'); }
  finally { showLoading(false); }
}

async function updateReportStatus(reportId, status, selectEl) {
  showLoading(true);
  try {
    const res = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=update_report_priority&report_id=${reportId}&status=${encodeURIComponent(status)}` });
    const r = await res.json();
    if (r.success) { showToast(`Status updated to ${status}`, 'success'); }
    else { showToast(r.message, 'error'); }
  } catch { showToast('Network error', 'error'); }
  finally { showLoading(false); }
}

async function resolveIncident(id, btn) {
  if (!confirm('Mark this incident as resolved?')) return;
  showLoading(true);
  try {
    const res = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=resolve_incident&incident_id=${id}` });
    const r = await res.json();
    if (r.success) {
      showToast('Incident resolved.', 'success');
      const row = document.getElementById('inc-row-' + id);
      if (row) {
        row.cells[3].innerHTML = `<span class="status-badge status-resolved">Resolved</span>`;
        row.cells[5].innerHTML = `<span style="color:var(--c-success);font-size:0.82rem;font-weight:700;"><i class="bi bi-check-circle-fill"></i> Resolved</span>`;
      }
      if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-check2-circle"></i> Done'; }
    } else showToast(r.message, 'error');
  } catch { showToast('Network error', 'error'); }
  finally { showLoading(false); }
}

async function dashResolve(id, btn) {
  await resolveIncident(id, btn);
  if (btn && btn.disabled) {
    const row = document.getElementById('dash-inc-' + id);
    const cell = row ? row.querySelector('.dash-status') : null;
    if (cell) cell.innerHTML = '<span class="dn-pill" style="--pc:#059669;"><i></i>Resolved</span>';
  }
}
// -- MAP (Google Maps Engine) --
let officialMap = null;
let officialInfoWindow = null;
const mapMarkers = { farms: [], livestock: [], incidents: [], reports: [] };

function getLegendHTML(layer) {
  if (layer === 'farms') return `<h4><i class="bi bi-house-door" style="color:var(--c-brand-accent);"></i> Farm Legend</h4><div class="leg-item"><div class="leg-dot" style="background:#10B981;"></div> Approved</div><div class="leg-item"><div class="leg-dot" style="background:#EF4444;"></div> Rejected</div><div class="leg-item"><div class="leg-dot" style="background:#F59E0B;"></div> Pending</div>`;
  if (layer === 'livestock') return `<h4><i class="bi bi-tags" style="color:#3B82F6;"></i> Livestock Density</h4><div class="leg-item"><div class="leg-dot" style="background:#10B981;"></div> Low (&lt;10)</div><div class="leg-item"><div class="leg-dot" style="background:#F59E0B;"></div> Medium (10-20)</div><div class="leg-item"><div class="leg-dot" style="background:#EF4444;"></div> High (&gt;20)</div>`;
  if (layer === 'incidents') return `<h4><i class="bi bi-exclamation-triangle" style="color:#EF4444;"></i> Incident Priority</h4><div class="leg-item"><div class="leg-dot" style="background:#FBBF24;"></div> Low</div><div class="leg-item"><div class="leg-dot" style="background:#F97316;"></div> Medium</div><div class="leg-item"><div class="leg-dot" style="background:#EF4444;"></div> High/Critical</div><div class="leg-item"><div class="leg-dot" style="background:#10B981;"></div> Resolved</div>`;
  if (layer === 'reports') return `<h4><i class="bi bi-file-earmark-text" style="color:#3B82F6;"></i> Public Reports</h4><div class="leg-item"><div class="leg-dot" style="background:#F59E0B;"></div> Pending</div><div class="leg-item"><div class="leg-dot" style="background:#3B82F6;"></div> Reviewed</div><div class="leg-item"><div class="leg-dot" style="background:#10B981;"></div> Resolved</div><div class="leg-item"><div class="leg-dot" style="background:#6B7280;"></div> Closed</div>`;
  return '';
}

function initOfficialMap() {
  if (officialMap) {
    if (typeof google !== 'undefined' && google.maps) {
      google.maps.event.trigger(officialMap, 'resize');
    }
    return;
  }
  if (typeof google === 'undefined' || !google.maps) {
    const el = document.getElementById('officialMap');
    if (el && !el.querySelector('iframe')) {
      el.innerHTML = '<iframe src="<?= app_url('maps/farm-map.php?municipality=' . urlencode($officerMuni)) ?>" style="width:100%;height:100%;min-height:540px;border:none;border-radius:12px;" title="<?= htmlspecialchars($officerMuni) ?> GIS Map"></iframe>';
      const leg = document.getElementById('mapLegend');
      if (leg) leg.style.display = 'none';
    }
    return;
  }
  const el = document.getElementById('officialMap');
  if (!el) return;

  officialMap = new google.maps.Map(el, {
    center: { lat: 13.803, lng: 122.858 },
    zoom: 10,
    mapTypeId: 'roadmap',
    mapTypeControl: true,
    fullscreenControl: true,
    streetViewControl: false
  });

  officialInfoWindow = new google.maps.InfoWindow();

  buildFarmsLayer();
  buildIncidentsLayer();
  buildPublicReportsLayer();
  buildLivestockLayer();

  showLayerMarkers('farms');
  updateLegend('farms');
}

window.initGoogleOfficialMap = function() {
  const mapSec = document.getElementById('sec-map');
  if (mapSec && mapSec.classList.contains('active')) {
    initOfficialMap();
  }
};

function buildFarmsLayer() {
  farmsData.forEach(f => {
    const lat = parseFloat(f.latitude);
    const lng = parseFloat(f.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng) || (lat === 0 && lng === 0)) return;
    const st = String(f.status || 'Pending');
    const color = (st === 'Approved' || st === 'accepted') ? '#10B981' : (st === 'Pending' || st === 'pending') ? '#F59E0B' : '#EF4444';
    const isReg = f.source === 'farm_registration';
    const ownerDisplay = f.ownerName || ((f.firstName||'')+' '+(f.lastName||'')).trim() || 'Unassigned';
    const marker = new google.maps.Marker({
      position: { lat, lng },
      title: f.name,
      icon: {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 7,
        fillColor: color,
        fillOpacity: 0.9,
        strokeColor: '#ffffff',
        strokeWeight: 2
      }
    });
    marker.addListener('click', () => {
      officialInfoWindow.setContent(
        `<div style="min-width:190px;font-family:'DM Sans',sans-serif;padding:6px;">` +
        (isReg ? `<span style="display:inline-block;font-size:0.68rem;font-weight:700;background:#eff6ff;color:#1d4ed8;padding:1px 6px;border-radius:4px;margin-bottom:4px;">Farm Registration</span><br>` : '') +
        `<b style="font-size:0.95rem;">${escapeHtml(f.name)}</b><br>` +
        `<span style="color:#64748b;font-size:0.8rem;">Owner:</span> ${escapeHtml(ownerDisplay)}<br>` +
        `<span style="color:#64748b;font-size:0.8rem;">Livestock:</span> ${f.total_livestock||0} heads<br>` +
        `<span style="color:#64748b;font-size:0.8rem;">Status:</span> <b style="color:${color}">${escapeHtml(st)}</b>` +
        `</div>`
      );
      officialInfoWindow.open(officialMap, marker);
    });
    mapMarkers.farms.push(marker);
  });
}

function buildIncidentsLayer() {
  incidentsData.forEach(i => {
    if (!i.latitude || !i.longitude) return;
    const color = (i.status === 'Resolved' || i.status === 'Closed') ? '#10B981' : (i.priority === 'Critical' || i.priority === 'High') ? '#EF4444' : i.priority === 'Medium' ? '#F97316' : '#FBBF24';
    const marker = new google.maps.Marker({
      position: { lat: parseFloat(i.latitude), lng: parseFloat(i.longitude) },
      title: i.title,
      icon: {
        path: 'M -8,6 L 0,-10 L 8,6 Z',
        scale: 1.1,
        fillColor: color,
        fillOpacity: 0.9,
        strokeColor: '#ffffff',
        strokeWeight: 1.5
      }
    });
    marker.addListener('click', () => {
      officialInfoWindow.setContent(
        `<div style="font-family:'DM Sans',sans-serif;padding:6px;min-width:180px;">` +
        `<b>${escapeHtml(i.title)}</b><br>` +
        `<span style="color:#64748b;">Priority:</span> <b>${escapeHtml(i.priority)}</b><br>` +
        `<span style="color:#64748b;">Status:</span> ${escapeHtml(i.status)}` +
        `</div>`
      );
      officialInfoWindow.open(officialMap, marker);
    });
    mapMarkers.incidents.push(marker);
  });
}

function buildPublicReportsLayer() {
  const statusColors = { 'Pending': '#F59E0B', 'Reviewed': '#3B82F6', 'Resolved': '#10B981', 'Closed': '#6B7280' };
  publicReportsData.forEach(r => {
    if (!r.latitude || !r.longitude) return;
    const color = statusColors[r.status] || '#F59E0B';
    const desc = (r.description || '').substring(0, 80) + ((r.description || '').length > 80 ? '...' : '');
    const dateStr = r.createdAt ? new Date(r.createdAt).toLocaleDateString('en-PH', {month:'short',day:'numeric',year:'numeric'}) : '';
    const marker = new google.maps.Marker({
      position: { lat: parseFloat(r.latitude), lng: parseFloat(r.longitude) },
      title: `Report #${r.id}`,
      icon: {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 6,
        fillColor: color,
        fillOpacity: 0.9,
        strokeColor: '#ffffff',
        strokeWeight: 2
      }
    });
    marker.addListener('click', () => {
      officialInfoWindow.setContent(
        `<div style="font-family:'DM Sans',sans-serif;min-width:200px;padding:6px;">` +
        `<div style="font-weight:700;margin-bottom:4px;"><i class="bi bi-clipboard2-check"></i> Report #${r.id}</div>` +
        `<div style="font-size:0.8rem;margin-bottom:2px;"><b>Type:</b> ${escapeHtml(r.reportType)}</div>` +
        `<div style="font-size:0.78rem;color:#555;margin-bottom:6px;line-height:1.4;">${escapeHtml(desc)}</div>` +
        `<div style="font-size:0.75rem;"><b>Status:</b> <span style="color:${color};font-weight:700;">${escapeHtml(r.status)}</span></div>` +
        `<div style="font-size:0.72rem;color:#888;margin-top:2px;">${dateStr}</div>` +
        `</div>`
      );
      officialInfoWindow.open(officialMap, marker);
    });
    mapMarkers.reports.push(marker);
  });
}

function buildLivestockLayer() {
  farmsData.forEach(f => {
    if (!f.latitude || !f.longitude) return;
    const qty = parseInt(f.total_livestock) || 0;
    const color = qty < 10 ? '#10B981' : qty <= 20 ? '#F59E0B' : '#EF4444';
    const r = Math.min(Math.max(5, Math.sqrt(qty) * 2), 16);
    const marker = new google.maps.Marker({
      position: { lat: parseFloat(f.latitude), lng: parseFloat(f.longitude) },
      title: `${f.name}: ${qty} heads`,
      icon: {
        path: google.maps.SymbolPath.CIRCLE,
        scale: r,
        fillColor: color,
        fillOpacity: 0.82,
        strokeColor: '#ffffff',
        strokeWeight: 2
      }
    });
    marker.addListener('click', () => {
      officialInfoWindow.setContent(
        `<div style="font-family:'DM Sans',sans-serif;padding:6px;">` +
        `<b>${escapeHtml(f.name)}</b><br>` +
        `Livestock: <b style="color:${color};">${qty} heads</b>` +
        `</div>`
      );
      officialInfoWindow.open(officialMap, marker);
    });
    mapMarkers.livestock.push(marker);
  });
}

function showLayerMarkers(type) {
  Object.keys(mapMarkers).forEach(k => {
    mapMarkers[k].forEach(m => m.setMap(null));
  });
  if (mapMarkers[type]) {
    mapMarkers[type].forEach(m => m.setMap(officialMap));
  }
}

function switchMapLayer(type, btn) {
  if (!officialMap) return;
  document.querySelectorAll('.toggle-map-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  showLayerMarkers(type);
  updateLegend(type);
}

function updateLegend(layer) { document.getElementById('mapLegend').innerHTML = getLegendHTML(layer); }

function fitMapPH() {
  if (!officialMap) return;
  const bounds = new google.maps.LatLngBounds(
    new google.maps.LatLng(13.645, 122.505),
    new google.maps.LatLng(13.955, 123.18)
  );
  officialMap.fitBounds(bounds);
}

function escapeHtml(value) {
  return String(value ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function animateBars() {
  document.querySelectorAll('.bar-fill, .an-bar-fill').forEach(bar => {
    const pct = bar.getAttribute('data-pct');
    setTimeout(() => { bar.style.width = pct + '%'; }, 100);
  });
}

function switchDaTab(tabName) {
  if (tabName === 'security') {
    document.getElementById('daOfficerPwForm')?.scrollIntoView({behavior:'smooth'});
  }
}

function previewDaPhoto(input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
      showToast('Photo is too large (max 5MB)', 'warn');
      input.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
      const preview = document.getElementById('daEditLiveAvatar');
      if (preview) {
        preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;">`;
      }
    };
    reader.readAsDataURL(file);
  }
}

if (!window.copyTagId) {
  window.copyTagId = function(tagId, el) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(tagId).then(() => {
        showToast(`Copied: ${tagId}`, 'success');
        if (el) {
          const orig = el.textContent;
          el.textContent = 'Copied!';
          setTimeout(() => { el.textContent = orig; }, 1800);
        }
      }).catch(() => fallbackCopy(tagId));
    } else {
      fallbackCopy(tagId);
    }
  };
  function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast(`Copied: ${text}`, 'success');
  }
}

const daOfficerPwForm = document.getElementById('daOfficerPwForm');
if (daOfficerPwForm) {
  daOfficerPwForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnUpdateOfficerPw');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating...'; }
    try {
      const fd = new FormData(this);
      const res = await fetch(window.location.href, { method: 'POST', body: fd });
      const r = await res.json();
      if (r.success) {
        showToast(r.message || 'Password updated successfully!', 'success');
        this.reset();
        closePasswordModal();
      } else {
        showToast(r.message || 'Password update failed', 'error');
      }
    } catch {
      showToast('Network error while updating password', 'error');
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-key-fill"></i> Update Password'; }
    }
  });
}

function openEditModal() { document.getElementById('editProfileModal').classList.add('active'); document.body.style.overflow = 'hidden'; }
function closeEditModal() { document.getElementById('editProfileModal').classList.remove('active'); document.body.style.overflow = ''; }
document.getElementById('editProfileModal').addEventListener('click', function(e) { if (e.target === this) closeEditModal(); });

function openPasswordModal() { document.getElementById('passwordModal').classList.add('active'); document.body.style.overflow = 'hidden'; }
function closePasswordModal() { document.getElementById('passwordModal').classList.remove('active'); document.body.style.overflow = ''; }
document.getElementById('passwordModal')?.addEventListener('click', function(e) { if (e.target === this) closePasswordModal(); });

async function submitProfile() {
  showLoading(true);
  try {
    const fd = new FormData(document.getElementById('profileForm'));
    const res = await fetch(window.location.href, { method:'POST', body: fd });
    const r = await res.json();
    if (r.success) { showToast(r.message || 'Profile updated!', 'success'); closeEditModal(); setTimeout(() => location.reload(), 1000); }
    else showToast(r.message, 'error');
  } catch { showToast('Update failed', 'error'); }
  finally { showLoading(false); }
}

// Shared downloader: fetches the styled export from this page and saves the file
function agxDownload(exportKey, format, label) {
  const fmt = (format === 'excel') ? 'xlsx' : format;
  showToast(`Generating ${label} (${fmt.toUpperCase()})...`, 'info');
  const url = window.location.pathname + '?export=' + encodeURIComponent(exportKey) + '&format=' + encodeURIComponent(fmt);
  return fetch(url, { credentials: 'same-origin' }).then(async r => {
    if (!r.ok) throw new Error((await r.text()) || 'Export failed');
    const cd = r.headers.get('Content-Disposition') || '';
    const mt = cd.match(/filename="?([^";]+)"?/i);
    const name = mt ? mt[1] : `AgriTrace_${exportKey}_${new Date().toISOString().slice(0,10)}.${fmt}`;
    return r.blob().then(blob => ({ blob, name }));
  }).then(({ blob, name }) => {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = name;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    showToast('Downloaded ' + fmt.toUpperCase() + '!', 'success');
  }).catch(e => showToast(e.message || 'Download failed.', 'error'));
}

function downloadReport(format) { agxDownload('analytics', format, 'report'); }

function showLoading(v) { document.getElementById('loadingOverlay').style.display = v ? 'flex' : 'none'; }

function showToast(msg, type) {
  const bg = type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#3B82F6';
  const t = document.createElement('div');
  t.style.cssText = `position:fixed;top:80px;right:20px;background:${bg};color:#fff;padding:12px 20px;border-radius:10px;font-weight:700;z-index:9999;transform:translateX(420px);transition:transform 0.3s ease;box-shadow:0 8px 24px rgba(0,0,0,0.2);font-family:'DM Sans',sans-serif;font-size:0.88rem;max-width:300px;`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.style.transform = 'translateX(0)', 50);
  setTimeout(() => { t.style.transform = 'translateX(420px)'; setTimeout(() => t.remove(), 400); }, 3000);
}

// -- YEARLY TREND DATA from PHP --
const yearlyTrend = <?= json_encode($yearly_trend) ?>;
const typeColors = {
  Cattle:'#3B82F6', Swine:'#F59E0B', Poultry:'#EC4899',
  Goat:'#8B5CF6',   Sheep:'#10B981', Horse:'#F97316',
  Rabbit:'#EF4444', Carabao:'#6B7280', Other:'#64748B'
};
const typeEmojis = {
  Cattle:'🐄', Swine:'🐖', Poultry:'🐓', Goat:'🐐',
  Sheep:'🐑', Horse:'🐴', Rabbit:'🐇', Carabao:'🐃', Buffalo:'🐃',
  Duck:'🦆', Turkey:'🦃', Other:'🐾'
};
 
const anComposition = <?= json_encode(array_map(fn($lt) => [
  'label' => $type_display[$lt['type']] ?? $lt['type'],
  'value' => (int)$lt['total_qty'],
  'color' => $type_colors[$lt['type']] ?? '#64748B',
], $analytics['livestock_by_type'])) ?>;

function initTrendChart() {
  if (typeof Chart === 'undefined') return;
  Chart.defaults.font.family = "'DM Sans', sans-serif";
  Chart.defaults.color = '#64748B';
  initDonutChart();
  initLineChart();
}

function initDonutChart() {
  const canvas = document.getElementById('animalDonutChart');
  if (!canvas || !anComposition.length) return;
  const total = anComposition.reduce((s, d) => s + d.value, 0) || 1;
  new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: anComposition.map(d => d.label),
      datasets: [{
        data: anComposition.map(d => d.value),
        backgroundColor: anComposition.map(d => d.color),
        borderColor: '#fff', borderWidth: 3, hoverOffset: 8, borderRadius: 6, spacing: 1
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '74%',
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#0F172A', padding: 12, cornerRadius: 10, displayColors: true, boxPadding: 4,
          callbacks: { label: c => ` ${c.label}: ${c.parsed.toLocaleString()} heads (${((c.parsed / total) * 100).toFixed(1)}%)` }
        }
      }
    }
  });
}

function initLineChart() {
  const canvas = document.getElementById('livestockTrendChart');
  if (!canvas) return;
  const years = Object.keys(yearlyTrend).sort();
  if (!years.length) {
    canvas.parentElement.innerHTML = '<div class="an-empty"><i class="bi bi-graph-up"></i><p>No yearly data yet.</p></div>';
    return;
  }
  const allTypes = new Set();
  Object.values(yearlyTrend).forEach(yr => Object.keys(yr).forEach(t => allTypes.add(t)));

  const datasets = [...allTypes].map(type => {
    const color = typeColors[type] || '#64748B';
    return {
      label: (typeEmojis[type] || '') + ' ' + type,
      data: years.map(yr => yearlyTrend[yr]?.[type] || 0),
      borderColor: color,
      backgroundColor: (c) => {
        const { ctx, chartArea } = c.chart;
        if (!chartArea) return color + '22';
        const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
        g.addColorStop(0, color + '33'); g.addColorStop(1, color + '00');
        return g;
      },
      fill: true, tension: 0.4, borderWidth: 2.5,
      pointRadius: 4, pointHoverRadius: 7, pointBackgroundColor: '#fff', pointBorderColor: color, pointBorderWidth: 2.5
    };
  });

  new Chart(canvas, {
    type: 'line',
    data: { labels: years, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { font: { size: 12, weight: '600' }, padding: 16, usePointStyle: true, pointStyle: 'circle', boxWidth: 8 } },
        tooltip: {
          backgroundColor: '#0F172A', padding: 12, cornerRadius: 10, boxPadding: 4,
          titleFont: { weight: 'bold' },
          callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString()} heads` }
        }
      },
      scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { font: { weight: '600' } } },
        y: { beginAtZero: true, border: { display: false }, grid: { color: '#F1F5F9' }, ticks: { callback: v => v.toLocaleString() } }
      }
    }
  });
}
// -- RESOLVE + AUTO -- ARCHIVE (incidents) ------------------------
async function resolveAndArchive(id, btn) {
  if (!confirm('Mark this incident as resolved? It will move to Archive.')) return;
  showLoading(true);
  try {
    const res = await fetch(window.location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=resolve_incident&incident_id=${id}`
    });
    const r = await res.json();
    if (r.success) {
      showToast('Incident resolved & archived.', 'success');
      // Remove from active list
      const row = document.getElementById('inc-row-' + id);
      if (row) { row.style.transition = 'opacity 0.4s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 420); }
    } else showToast(r.message, 'error');
  } catch { showToast('Network error', 'error'); }
  finally { showLoading(false); }
}
 
// -- UPDATE REPORT STATUS + AUTO -- ARCHIVE -----------------------
async function updateReportStatusAndArchive(reportId, status, selectEl) {
  showLoading(true);
  try {
    const res = await fetch(window.location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=update_report_priority&report_id=${reportId}&status=${encodeURIComponent(status)}`
    });
    const r = await res.json();
    if (r.success) {
      showToast(`Status updated to ${status}`, 'success');
      if (status === 'Resolved' || status === 'Closed') {
        const row = document.getElementById('rpt-row-' + reportId);
        if (row) { row.style.transition = 'opacity 0.4s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 420); }
      }
    } else showToast(r.message, 'error');
  } catch { showToast('Network error', 'error'); }
  finally { showLoading(false); }
}
 
// -- FARM REVIEW MODAL --
const farmsFullData = <?= json_encode($all_farms) ?>;
const allIncidentsData = <?= json_encode($all_incidents) ?>;
 
async function openFarmReview(farmId) {
  const modal = document.getElementById('farmReviewModal');
  const body  = document.getElementById('farmReviewBody');
  const actions = document.getElementById('farmReviewActions');
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
 
  const farm = farmsFullData.find(f => parseInt(f.id) === parseInt(farmId));
  if (!farm) { body.innerHTML = '<p style="color:red;padding:20px;">Farm not found.</p>'; return; }

  const appType = farm.application_type || 'New';
  const isRenewal = String(appType).toLowerCase() === 'renewal';
  const rawOwn = String(farm.ownership || 'Private').toLowerCase();
  const isGovt = rawOwn === 'govt' || rawOwn === 'government';
  const ownershipLabel = isGovt ? 'Government' : 'Private';

  // Build review HTML
  const yesNo = v => v === 'Yes' ? '<span style="color:#059669;font-weight:700;"><i class="bi bi-check-circle"></i> Yes</span>' : '<span style="color:#dc2626;font-weight:700;"><i class="bi bi-x-circle"></i> No</span>';
  const val   = v => v ? `<strong>${v}</strong>` : '<span style="color:#94a3b8;"> - </span>';

  body.innerHTML = `
    ${isRenewal ? `
      <div style="background:#eef2ff;border:1.5px solid #c7d2fe;border-radius:12px;padding:14px 16px;margin-bottom:18px;display:flex;align-items:flex-start;gap:12px;">
        <div style="width:36px;height:36px;border-radius:50%;background:#e0e7ff;color:#4338ca;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">
          <i class="bi bi-arrow-repeat"></i>
        </div>
        <div style="font-size:0.85rem;color:#3730a3;line-height:1.45;">
          <strong style="font-size:0.92rem;display:block;margin-bottom:2px;"><i class="bi bi-arrow-repeat"></i> Farm Renewal Application (Farm ID #${farm.id})</strong>
          This application is a <strong>Renewal</strong> for an already registered farm. Approving this request updates and renews the farm registration and digital certification in the database without creating duplicate entries.
        </div>
      </div>` : `
      <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:12px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:12px;">
        <div style="width:34px;height:34px;border-radius:50%;background:#dcfce7;color:#15803d;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
          <i class="bi bi-plus-circle"></i>
        </div>
        <div style="font-size:0.85rem;color:#166534;">
          <strong>New Farm Registration:</strong> Initial registration application for farm accreditation and digital certification.
        </div>
      </div>
    `}
    ${farm.photo_url ? `<img src="${farm.photo_url}" class="review-photo" alt="Farm photo">` : ''}
    <div class="review-section">
      <h4><i class="bi bi-flower1"></i> Farm Information</h4>
      <div class="review-grid">
        <div class="review-item"><div class="r-label">Farm Name</div><div class="r-value">${farm.name}</div></div>
        <div class="review-item"><div class="r-label">Application Type</div><div class="r-value">
          <span style="font-weight:700;font-size:0.8rem;padding:3px 9px;border-radius:6px;${isRenewal ? 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;' : 'background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;'}">
            <i class="bi ${isRenewal ? 'bi-arrow-repeat' : 'bi-plus-circle'}"></i> ${appType}
          </span>
        </div></div>
        <div class="review-item"><div class="r-label">Ownership</div><div class="r-value">
          <span style="font-weight:700;font-size:0.8rem;padding:3px 9px;border-radius:6px;${isGovt ? 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;' : 'background:#f1f5f9;color:#334155;border:1px solid #e2e8f0;'}">
            <i class="bi ${isGovt ? 'bi-building' : 'bi-person'}"></i> ${ownershipLabel}
          </span>
        </div></div>
        <div class="review-item"><div class="r-label">Farm Type</div><div class="r-value">${farm.type}</div></div>
        <div class="review-item"><div class="r-label">Registration Status</div><div class="r-value"><span class="status-badge status-${(farm.status||'').toLowerCase()}">${farm.status}</span></div></div>
        <div class="review-item"><div class="r-label">Address</div><div class="r-value">${farm.address||' - '}</div></div>
        <div class="review-item"><div class="r-label">Area (ha)</div><div class="r-value">${farm.area||' - '}</div></div>
        <div class="review-item"><div class="r-label">Region</div><div class="r-value">${farm.region||' - '}</div></div>
        <div class="review-item"><div class="r-label">Business Type</div><div class="r-value">${farm.business_type||' - '}</div></div>
      </div>
    </div>
    <div class="review-section">
      <h4><i class="bi bi-person-fill"></i> Petitioner / Owner</h4>
      <div class="review-grid">
        <div class="review-item"><div class="r-label">Full Name</div><div class="r-value">${farm.petitioner_firstname||''} ${farm.petitioner_surname||''}</div></div>
        <div class="review-item"><div class="r-label">Owner (account)</div><div class="r-value">${(farm.firstName||'')+' '+(farm.lastName||'')}</div></div>
        <div class="review-item"><div class="r-label">Sex</div><div class="r-value">${farm.pet_sex||' - '}</div></div>
        <div class="review-item"><div class="r-label">Civil Status</div><div class="r-value">${farm.pet_civil_status||' - '}</div></div>
        <div class="review-item"><div class="r-label">Date of Birth</div><div class="r-value">${farm.pet_dob||' - '}</div></div>
        <div class="review-item"><div class="r-label">Place of Birth</div><div class="r-value">${farm.pet_pob||' - '}</div></div>
        <div class="review-item"><div class="r-label">Barangay</div><div class="r-value">${farm.pet_barangay||' - '}</div></div>
        <div class="review-item"><div class="r-label">Municipality</div><div class="r-value">${farm.pet_municipality||' - '}</div></div>
        <div class="review-item"><div class="r-label">Education</div><div class="r-value">${farm.pet_education||' - '}</div></div>
        <div class="review-item"><div class="r-label">Religion</div><div class="r-value">${farm.pet_religion||' - '}</div></div>
        <div class="review-item"><div class="r-label">Household Members</div><div class="r-value">M: ${farm.pet_male_members||0} / F: ${farm.pet_female_members||0}</div></div>
        <div class="review-item"><div class="r-label">Household Head?</div><div class="r-value">${yesNo(farm.hh_head)}</div></div>
        <div class="review-item"><div class="r-label">PWD?</div><div class="r-value">${yesNo(farm.pwd)}</div></div>
        <div class="review-item"><div class="r-label">4P's Beneficiary?</div><div class="r-value">${yesNo(farm.fourps)}</div></div>
        <div class="review-item"><div class="r-label">Indigenous Group?</div><div class="r-value">${yesNo(farm.indigenous)}${farm.indigenous_specify?` (${farm.indigenous_specify})`:''}
        </div></div>
        <div class="review-item"><div class="r-label">Gov't ID?</div><div class="r-value">${yesNo(farm.has_gov_id)}${farm.gov_id_number?'  -  '+farm.gov_id_number:''}</div></div>
        <div class="review-item"><div class="r-label">Farmers Assoc?</div><div class="r-value">${yesNo(farm.farmers_assoc)}${farm.farmers_assoc_name?'  -  '+farm.farmers_assoc_name:''}</div></div>
        <div class="review-item"><div class="r-label">Emergency Contact</div><div class="r-value">${farm.emergency_contact_name||' - '} ${farm.emergency_contact_no||''}</div></div>
        <div class="review-item"><div class="r-label">Income (Farming)</div><div class="r-value">PHP ${parseFloat(farm.income_farming||0).toLocaleString()}</div></div>
        <div class="review-item"><div class="r-label">Income (Non-farming)</div><div class="r-value">PHP ${parseFloat(farm.income_non_farming||0).toLocaleString()}</div></div>
      </div>
    </div>
    <div class="review-section">
      <h4>Cattle Livestock Registered (${parseInt(farm.total_livestock||0).toLocaleString()} heads)</h4>
      <div id="reviewLivestockList"><p style="color:#94a3b8;font-size:0.85rem;">Loading livestock...</p></div>
    </div>
    <div class="review-section">
      <h4><i class="bi bi-exclamation-triangle-fill"></i> Farm Incidents (${allIncidentsData.filter(i=>parseInt(i.farmId)===parseInt(farmId)).length})</h4>
      ${allIncidentsData.filter(i=>parseInt(i.farmId)===parseInt(farmId)).length === 0
        ? '<p style="color:#94a3b8;font-size:0.85rem;">No incidents recorded for this farm.</p>'
        : allIncidentsData.filter(i=>parseInt(i.farmId)===parseInt(farmId)).map(i=>`
          <div class="review-livestock-row">
            <span class="status-badge priority-${i.priority.toLowerCase()}">${i.priority}</span>
            <span style="font-weight:600;font-size:0.85rem;">${i.title}</span>
            <span style="color:#64748b;font-size:0.78rem;margin-left:auto;">${i.status}</span>
          </div>`).join('')
      }
    </div>
    <div class="review-section">
      <h4><i class="bi bi-geo-alt-fill"></i> GPS Location</h4>
      ${farm.latitude && farm.longitude
        ? `<p style="font-size:0.85rem;color:#374151;font-weight:600;"><i class="bi bi-geo-alt-fill"></i> ${farm.latitude}, ${farm.longitude}
           <a href="https://maps.google.com/?q=${farm.latitude},${farm.longitude}" target="_blank" rel="noopener" style="color:var(--c-brand-accent);margin-left:8px;">Open in Maps <i class="bi bi-arrow-up-right"></i></a></p>`
        : '<p style="color:#94a3b8;font-size:0.85rem;">No GPS data recorded.</p>'
      }
    </div>
  `;
 
  // Action buttons
  if (farm.status === 'Pending') {
    actions.innerHTML = `
      <button class="btn-cancel" onclick="closeFarmReview()">Close</button>
      <button class="btn btn-danger" onclick="rejectFarm(${farm.id});closeFarmReview();" style="padding:11px 18px;border-radius:10px;"><i class="bi bi-x-circle"></i> Reject</button>
      <button class="btn-save" onclick="approveFarm(${farm.id});closeFarmReview();"><i class="bi bi-check2"></i> ${isRenewal ? 'Approve Renewal' : 'Approve Farm'}</button>
    `;
  } else {
    actions.innerHTML = `<button class="btn-cancel" onclick="closeFarmReview()">Close</button>
      <span style="padding:11px 18px;font-weight:700;color:${farm.status==='Approved'?'#059669':'#dc2626'};">${farm.status==='Approved'?'<i class="bi bi-check-circle"></i> Approved':'<i class="bi bi-x-circle"></i> Rejected'}</span>`;
  }
 
  // Fetch livestock for this farm
  try {
    const lvRes = await fetch(`<?= app_url('api/get_farm_livestock.php') ?>?farm_id=${farmId}`);
    if (lvRes.ok) {
      const lvData = await lvRes.json();
      const lvEl = document.getElementById('reviewLivestockList');
      if (lvEl && lvData.success && lvData.livestock && lvData.livestock.length) {
        lvEl.innerHTML = lvData.livestock.map(lv => `
          <div class="review-livestock-row">
            <span style="font-size:1.2rem;"><i class="bi bi-tag-fill" style="color:var(--c-brand);"></i></span>
            <div>
              <div style="font-weight:700;font-size:0.85rem;">${lv.tagId}  -  ${lv.type}${lv.breed?' ('+lv.breed+')':''}</div>
              <div style="font-size:0.75rem;color:#64748b;">Qty: ${parseInt(lv.qty).toLocaleString()} &bull; ${lv.healthStatus} ${lv.age ? '&bull; ' + lv.age + ' mos' : ''} ${lv.weight ? '&bull; ' + lv.weight + ' kg' : ''} ${lv.vaccinationStatus ? '&bull; Vax: ' + lv.vaccinationStatus : ''}</div>
              ${lv.notes ? `<div style="font-size:0.72rem;color:#475569;margin-top:2px;font-style:italic;">${lv.notes}</div>` : ''}
            </div>
          </div>`).join('');
      } else if (lvEl) {
        lvEl.innerHTML = '<p style="color:#94a3b8;font-size:0.85rem;">No livestock records.</p>';
      }
    }
  } catch (e) {
    const lvEl = document.getElementById('reviewLivestockList');
    if (lvEl) lvEl.innerHTML = '<p style="color:#94a3b8;font-size:0.85rem;">Could not load livestock (api/get_farm_livestock.php needed).</p>';
  }
}
 
function closeFarmReview() {
  document.getElementById('farmReviewModal').classList.remove('active');
  document.body.style.overflow = '';
}
 
// -- ARCHIVE DOWNLOAD --
function downloadArchive(type, format) {
  agxDownload(type === 'incidents' ? 'archive_incidents' : 'archive_reports', format || 'xlsx', 'archive');
}
 
// -- INIT CHART when analytics tab is shown --
const _origNavTo = typeof navTo === 'function' ? navTo : null;
// Patch navTo to init chart
document.querySelectorAll('.nav-item').forEach(el => {
  el.addEventListener('click', () => {
    if (el.querySelector('span')?.textContent?.trim() === 'Analytics') {
      setTimeout(() => { initTrendChart(); animateBars(); }, 300);
    }
  });
});
// Also auto-init if already on analytics
if (document.getElementById('sec-reports')?.classList.contains('active')) {
  setTimeout(initTrendChart, 500);
}



// -- DA ACTION HANDLERS --
function showSuccessModal(msg) { if(typeof showToast === 'function') { showToast(msg, 'success'); } else { alert(msg); } }

function deleteFarmer(userId, farmerName) {
  if (!confirm(`Are you SURE you want to permanently delete farmer "${farmerName}" (ID #${userId})?\n\nWARNING: This will permanently remove their user account, farm registrations, livestock records, health history, certificates, and submissions.`)) return;
  const promptCheck = prompt(`Type DELETE to confirm permanent removal of "${farmerName}":`);
  if (promptCheck !== 'DELETE') {
    alert('Deletion cancelled: Confirmation text did not match.');
    return;
  }
  if (typeof showLoading === 'function') showLoading(true);
  const fd = new FormData();
  fd.append('action', 'delete_farmer');
  fd.append('user_id', userId);

  fetch('agriDA.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (typeof showLoading === 'function') showLoading(false);
      if (res.success) {
        showSuccessModal(res.message || 'Farmer account removed successfully!');
        setTimeout(() => location.reload(), 1200);
      } else {
        alert('Error: ' + (res.message || 'Failed to delete farmer account.'));
      }
    })
    .catch(err => {
      if (typeof showLoading === 'function') showLoading(false);
      alert('Network error deleting farmer account.');
    });
}

function viewFarmerDetails(farmerId) {
  const f = (typeof allFarmersData !== 'undefined' ? allFarmersData : []).find(x => parseInt(x.id) === parseInt(farmerId));
  if (!f) {
    alert('Farmer profile not found.');
    return;
  }
  const modal = document.getElementById('farmerDetailsModal');
  const nameEl = document.getElementById('fdModalName');
  const subEl = document.getElementById('fdModalSubtitle');
  const contentEl = document.getElementById('fdModalContent');
  const footLeftEl = document.getElementById('fdModalFooterLeft');

  const fullName = [f.firstName, f.lastName].filter(Boolean).join(' ') || 'Farmer';
  if (nameEl) nameEl.textContent = fullName;
  if (subEl) subEl.textContent = `User ID #${f.id} • Registered ${f.createdAt ? new Date(f.createdAt).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'}) : 'N/A'}`;

  // Find farms owned by this farmer
  const farms = (typeof farmsFullData !== 'undefined' ? farmsFullData : []).filter(fm => parseInt(fm.ownerId) === parseInt(farmerId));
  
  // Find submissions for this farmer
  const farmRegs = (typeof farmRegSubmissionsData !== 'undefined' ? farmRegSubmissionsData : []).filter(fr => parseInt(fr.farmer_id) === parseInt(farmerId));
  const rsbsaSubs = (typeof rsbsaSubmissionsData !== 'undefined' ? rsbsaSubmissionsData : []).filter(rs => parseInt(rs.farmer_id) === parseInt(farmerId));
  const pcicSubs = (typeof pcicSubmissionsData !== 'undefined' ? pcicSubmissionsData : []).filter(pc => parseInt(pc.farmer_id) === parseInt(farmerId));

  const isPending = (f.status || 'Active') === 'Pending';
  const isActive = (f.status || 'Active') === 'Active';
  const stColor = isPending ? '#f59e0b' : (isActive ? '#10b981' : '#ef4444');

  let html = `
    <!-- Summary Header Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:1.25rem;">
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Account Status</span>
        <div style="margin-top:4px;display:flex;align-items:center;gap:6px;">
          <span style="background:${stColor}15;color:${stColor};border:1px solid ${stColor}40;padding:2px 8px;border-radius:99px;font-size:.78rem;font-weight:700;">
            ${f.status || 'Active'}
          </span>
          <span style="font-size:.75rem;color:#64748b;">Role: ${f.role || 'Farmer'}</span>
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Registered Farms</span>
        <div style="font-size:1.15rem;font-weight:800;color:#059669;margin-top:2px;">
          ${farms.length} Farm${farms.length !== 1 ? 's' : ''}
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Livestock Inventory</span>
        <div style="font-size:1.15rem;font-weight:800;color:#2563eb;margin-top:2px;">
          ${parseInt(f.livestock_count || 0).toLocaleString()} Head${parseInt(f.livestock_count || 0) !== 1 ? 's' : ''}
        </div>
      </div>
    </div>

    <!-- Personal & Contact Information -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:1.25rem;">
      <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-person-lines-fill" style="color:#059669;"></i> Farmer Profile &amp; Contact Details
      </h4>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px;font-size:.82rem;">
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Email Address</span>
          <strong style="color:#1e293b;">${f.email || 'None'}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Mobile Number</span>
          <strong style="color:#1e293b;">${f.mobile || 'None recorded'}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Government / RSBSA ID</span>
          <strong style="color:#1e293b;">${f.gov_id || 'Not specified'}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Gender / Date of Birth</span>
          <strong style="color:#1e293b;">${f.gender || 'Not specified'} ${f.dob ? '• ' + f.dob : ''}</strong>
        </div>
        <div style="grid-column:1 / -1;">
          <span style="color:#64748b;display:block;font-size:.72rem;">Home Address</span>
          <span style="color:#1e293b;font-weight:600;">${f.profile_address || f.address || 'Address not logged'}</span>
        </div>
        ${f.emergency_contact ? `
        <div style="grid-column:1 / -1;">
          <span style="color:#64748b;display:block;font-size:.72rem;">Emergency Contact</span>
          <span style="color:#1e293b;">${f.emergency_contact}</span>
        </div>` : ''}
      </div>
    </div>

    <!-- Registered Farm Holdings & Registration Applications -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:1.25rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:6px;">
          <i class="bi bi-geo-alt-fill" style="color:#059669;"></i> Farm Holdings &amp; Registration Applications (${farms.length})
        </h4>
      </div>
  `;

  if (farms.length === 0) {
    html += `
      <div style="text-align:center;padding:1.5rem;background:#f8fafc;border-radius:10px;color:#64748b;font-size:.84rem;">
        <i class="bi bi-info-circle" style="font-size:1.4rem;display:block;margin-bottom:4px;color:#94a3b8;"></i>
        No farm registration records registered under this farmer account yet.
      </div>
    `;
  } else {
    html += `<div style="display:flex;flex-direction:column;gap:10px;">`;
    farms.forEach(farm => {
      const isPendingFarm = farm.status === 'Pending';
      const fStClr = isPendingFarm ? '#f59e0b' : (farm.status === 'Approved' ? '#10b981' : '#ef4444');
      const isRenewal = String(farm.application_type || '').toLowerCase() === 'renewal';
      
      const matchFolio = farmRegs.find(fr => parseInt(fr.farm_id) === parseInt(farm.id)) || farmRegs[0];

      html += `
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
            <div>
              <div style="display:flex;align-items:center;gap:8px;">
                <strong style="font-size:.95rem;color:#0f172a;">${farm.name}</strong>
                <span style="background:${fStClr}15;color:${fStClr};border:1px solid ${fStClr}40;padding:1px 7px;border-radius:99px;font-size:.7rem;font-weight:700;">
                  ${farm.status}
                </span>
                <span style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;padding:1px 7px;border-radius:99px;font-size:.7rem;font-weight:700;">
                  ${isRenewal ? 'Renewal' : 'New Application'}
                </span>
              </div>
              <div style="font-size:.78rem;color:#64748b;margin-top:3px;">
                <i class="bi bi-geo-alt"></i> ${farm.pet_barangay ? farm.pet_barangay + ', ' : ''}${farm.pet_municipality || 'Camarines Sur'} • ${farm.totalArea ? farm.totalArea + ' ha' : 'Area N/A'} • ${farm.ownership || 'Private'}
              </div>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <button type="button" class="an-btn an-btn-primary" style="padding:.35rem .75rem;font-size:.78rem;" onclick="closeFarmerDetails(); openFarmReview(${farm.id})">
                <i class="bi bi-file-earmark-text"></i> Review Application Details
              </button>
              ${matchFolio ? `
              <button type="button" class="an-btn" style="padding:.35rem .75rem;font-size:.78rem;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;" onclick="closeFarmerDetails(); viewSubmissionForm('farm_registration', ${matchFolio.id})" title="View original filled Folio Registration form">
                <i class="bi bi-eye"></i> View Folio
              </button>` : ''}
              <button type="button" class="an-btn" style="padding:.35rem .75rem;font-size:.78rem;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;" onclick="closeFarmerDetails(); openDaAddLivestockModal(${farm.id})" title="Add livestock to this farm">
                <i class="bi bi-plus-lg"></i> Add Livestock
              </button>
            </div>
          </div>
        </div>
      `;
    });
    html += `</div>`;
  }
  html += `</div>`;

  // Official Documents & Form Submissions (RSBSA & PCIC)
  if (rsbsaSubs.length > 0 || pcicSubs.length > 0) {
    html += `
      <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:1rem;">
        <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
          <i class="bi bi-files" style="color:#059669;"></i> Official Program Applications &amp; Folios
        </h4>
        <div style="display:flex;flex-direction:column;gap:8px;">
    `;
    rsbsaSubs.forEach(r => {
      html += `
        <div style="display:flex;justify-content:space-between;align-items:center;background:#f8fafc;padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;font-size:.82rem;">
          <div>
            <strong style="color:#0f172a;"><i class="bi bi-file-earmark-check-fill" style="color:#10b981;"></i> RSBSA Enrollment Folio</strong>
            <span style="color:#64748b;margin-left:6px;">Status: <b>${r.status || 'Submitted'}</b></span>
          </div>
          <button type="button" class="an-btn" style="padding:.3rem .65rem;font-size:.75rem;background:#fff;border:1px solid #cbd5e1;" onclick="closeFarmerDetails(); viewSubmissionForm('rsbsa', ${r.id})">
            <i class="bi bi-eye"></i> View RSBSA Form
          </button>
        </div>
      `;
    });
    pcicSubs.forEach(p => {
      html += `
        <div style="display:flex;justify-content:space-between;align-items:center;background:#f8fafc;padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;font-size:.82rem;">
          <div>
            <strong style="color:#0f172a;"><i class="bi bi-shield-check" style="color:#3b82f6;"></i> PCIC Insurance Application Folio</strong>
            <span style="color:#64748b;margin-left:6px;">Status: <b>${p.status || 'Submitted'}</b></span>
          </div>
          <button type="button" class="an-btn" style="padding:.3rem .65rem;font-size:.75rem;background:#fff;border:1px solid #cbd5e1;" onclick="closeFarmerDetails(); viewSubmissionForm('pcic', ${p.id})">
            <i class="bi bi-eye"></i> View PCIC Form
          </button>
        </div>
      `;
    });
    html += `</div></div>`;
  }

  if (contentEl) contentEl.innerHTML = html;

  // Build footer actions
  const farmerNameEscaped = fullName.replace(/'/g, "\\'");
  let footActions = '';
  if (isPending) {
    footActions += `<button type="button" class="an-btn an-btn-primary" style="font-size:.8rem;padding:.4rem .85rem;" onclick="closeFarmerDetails(); setFarmerStatus(${f.id}, 'Active')"><i class="bi bi-check-lg"></i> Approve Farmer Account</button>`;
  } else if (isActive) {
    footActions += `<button type="button" class="an-btn an-btn-danger" style="font-size:.8rem;padding:.4rem .85rem;" onclick="closeFarmerDetails(); setFarmerStatus(${f.id}, 'Inactive')"><i class="bi bi-pause-fill"></i> Deactivate Account</button>`;
  } else {
    footActions += `<button type="button" class="an-btn an-btn-primary" style="font-size:.8rem;padding:.4rem .85rem;" onclick="closeFarmerDetails(); setFarmerStatus(${f.id}, 'Active')"><i class="bi bi-play-fill"></i> Re-activate Account</button>`;
  }
  footActions += `<button type="button" class="an-btn" style="font-size:.8rem;padding:.4rem .85rem;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;" onclick="closeFarmerDetails(); deleteFarmer(${f.id}, '${farmerNameEscaped}')"><i class="bi bi-trash3-fill"></i> Delete Account</button>`;

  if (footLeftEl) footLeftEl.innerHTML = footActions;

  if (modal) modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeFarmerDetails() {
  const modal = document.getElementById('farmerDetailsModal');
  if (modal) modal.style.display = 'none';
  document.body.style.overflow = '';
}

function openDaAddLivestockModal(preferredFarmId) {
  const modal = document.getElementById('daAddLivestockModal');
  const form = document.getElementById('daAddLivestockForm');
  if (form) form.reset();
  if (preferredFarmId) {
    const sel = document.getElementById('daLvFarmSelect');
    if (sel) sel.value = preferredFarmId;
  }
  updateDaFarmingTypeVisual();
  if (modal) modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeDaAddLivestockModal() {
  const modal = document.getElementById('daAddLivestockModal');
  if (modal) modal.style.display = 'none';
  document.body.style.overflow = '';
}

function updateDaFarmingTypeVisual() {
  const isComm = document.querySelector('input[name="farming_type"]:checked')?.value === 'Commercial';
  const lblBy = document.getElementById('daFarmingTypeLblBackyard');
  const lblCm = document.getElementById('daFarmingTypeLblCommercial');
  if (lblBy) {
    lblBy.style.borderColor = isComm ? '#cbd5e1' : '#059669';
    lblBy.style.background = isComm ? '#fff' : '#ecfdf5';
  }
  if (lblCm) {
    lblCm.style.borderColor = isComm ? '#3b82f6' : '#cbd5e1';
    lblCm.style.background = isComm ? '#eff6ff' : '#fff';
  }
}

function generateDaTagId() {
  const spSelect = document.getElementById('daLvSpeciesSelect');
  const sp = spSelect?.value || 'LIV';
  const prefixMap = {
    'Cattle': 'CTL', 'Carabao': 'CRB', 'Swine': 'SWN',
    'Goat': 'GT', 'Sheep': 'SHP', 'Poultry': 'PLT',
    'Horse': 'HRS', 'Other': 'OTH'
  };
  const code = prefixMap[sp] || 'LIV';
  const yr = new Date().getFullYear();
  const rand = Math.random().toString(36).substring(2, 6).toUpperCase();
  const tagInput = document.getElementById('daLvTagId');
  if (tagInput) tagInput.value = `TAG-${code}-${yr}-${rand}`;
}

function onDaSpeciesChange() {
  const tagInput = document.getElementById('daLvTagId');
  if (tagInput && !tagInput.value) {
    generateDaTagId();
  }
}

let daScanStream = null;
let daScanInterval = null;
let daCurrentFacing = 'environment';

async function openDaTagScanner() {
  const modal = document.getElementById('daTagScanModal');
  if (modal) modal.style.display = 'flex';
  await startDaScanStream();
}

function closeDaTagScanner() {
  const modal = document.getElementById('daTagScanModal');
  if (modal) modal.style.display = 'none';
  if (daScanStream) {
    daScanStream.getTracks().forEach(t => t.stop());
    daScanStream = null;
  }
  if (daScanInterval) {
    clearInterval(daScanInterval);
    daScanInterval = null;
  }
}

async function startDaScanStream() {
  const video = document.getElementById('daTagVideo');
  const msg = document.getElementById('daScanStatusMsg');
  if (daScanStream) {
    daScanStream.getTracks().forEach(t => t.stop());
  }
  try {
    daScanStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: daCurrentFacing, width: { ideal: 1280 }, height: { ideal: 720 } },
      audio: false
    });
    if (video) {
      video.srcObject = daScanStream;
      video.setAttribute('playsinline', 'true');
      await video.play();
      startDaScanLoop();
    }
  } catch (err) {
    if (msg) msg.textContent = 'Camera unavailable. You can upload a photo or type manually.';
  }
}

function switchDaTagCam() {
  daCurrentFacing = daCurrentFacing === 'environment' ? 'user' : 'environment';
  startDaScanStream();
}

function onDaTagDetected(tagValue) {
  const cleanTag = String(tagValue).trim();
  if (!cleanTag) return;
  const tagInput = document.getElementById('daLvTagId');
  if (tagInput) tagInput.value = cleanTag;
  closeDaTagScanner();
  if (typeof showToast === 'function') {
    showToast(`Scanned Ear Tag: ${cleanTag}`, 'success');
  } else {
    alert(`Scanned Ear Tag: ${cleanTag}`);
  }
}

function startDaScanLoop() {
  const video = document.getElementById('daTagVideo');
  if (!('BarcodeDetector' in window)) {
    const msg = document.getElementById('daScanStatusMsg');
    if (msg) msg.textContent = 'BarcodeDetector not supported in this browser; upload a photo or enter tag.';
    return;
  }
  const detector = new BarcodeDetector({ formats: ['code_128', 'code_39', 'qr_code', 'ean_13', 'upc_a', 'data_matrix'] });
  if (daScanInterval) clearInterval(daScanInterval);
  daScanInterval = setInterval(async () => {
    if (!video || video.readyState < 2) return;
    try {
      const barcodes = await detector.detect(video);
      if (barcodes.length > 0) {
        onDaTagDetected(barcodes[0].rawValue);
      }
    } catch (e) {}
  }, 400);
}

async function handleDaTagImageUpload(input) {
  const file = input.files?.[0];
  if (!file) return;
  if ('BarcodeDetector' in window) {
    try {
      const bmp = await createImageBitmap(file);
      const detector = new BarcodeDetector({ formats: ['code_128', 'code_39', 'qr_code', 'ean_13', 'upc_a', 'data_matrix'] });
      const barcodes = await detector.detect(bmp);
      if (barcodes.length > 0) {
        onDaTagDetected(barcodes[0].rawValue);
        return;
      }
    } catch (e) {}
  }
  const manual = prompt('Tag detected from photo (or enter tag number found on ear tag):', file.name.replace(/\.[^/.]+$/, ''));
  if (manual) onDaTagDetected(manual);
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('daAddLivestockForm');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('btnSaveDaLv');
      if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...'; }
      try {
        const fd = new FormData(form);
        const res = await fetch('agriDA.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          showSuccessModal(data.message || 'Livestock added successfully!');
          closeDaAddLivestockModal();
          setTimeout(() => location.reload(), 1200);
        } else {
          alert('Error: ' + (data.message || 'Could not save livestock.'));
        }
      } catch (err) {
        alert('Network error saving livestock record.');
      } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle"></i> Save Livestock Record'; }
      }
    });
  }
});

// -- LIVESTOCK DETAILS MODAL --
function viewLivestockDetails(livestockId) {
  const lv = (typeof allLivestockData !== 'undefined' ? allLivestockData : []).find(x => parseInt(x.id) === parseInt(livestockId));
  if (!lv) {
    alert('Livestock record not found.');
    return;
  }
  const modal = document.getElementById('daLivestockDetailsModal');
  const tagTitle = document.getElementById('daLvDetTagTitle');
  const subTitle = document.getElementById('daLvDetSubtitle');
  const body = document.getElementById('daLvDetBody');

  if (tagTitle) tagTitle.textContent = lv.tagId || 'No Tag ID';
  if (subTitle) subTitle.textContent = `${lv.type || 'Livestock'} • Registered ${lv.createdAt ? new Date(lv.createdAt).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'}) : 'N/A'}`;

  const hSt = lv.healthStatus || 'Healthy';
  const hClr = hSt === 'Healthy' ? '#10b981' : (hSt === 'Sick' ? '#ef4444' : (hSt === 'Deceased' ? '#64748b' : '#3b82f6'));
  const farmingType = lv.farming_type || 'Backyard';
  const fTypeClr = farmingType === 'Commercial' ? '#3730a3' : '#065f46';
  const fTypeBg = farmingType === 'Commercial' ? '#e0e7ff' : '#ecfdf5';

  const healthRecs = (typeof allHealthRecordsData !== 'undefined' ? allHealthRecordsData : []).filter(h => parseInt(h.livestock_id) === parseInt(livestockId));

  let html = `
    <!-- Top Status Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:1.25rem;">
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Health Condition</span>
        <div style="margin-top:4px;">
          <span style="background:${hClr}15;color:${hClr};border:1px solid ${hClr}40;padding:2px 8px;border-radius:99px;font-size:.78rem;font-weight:700;">
            ${escapeHtml(hSt)}
          </span>
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Operation Type</span>
        <div style="margin-top:4px;">
          <span style="background:${fTypeBg};color:${fTypeClr};padding:2px 8px;border-radius:99px;font-size:.78rem;font-weight:700;">
            ${escapeHtml(farmingType)}
          </span>
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Vaccination Status</span>
        <div style="margin-top:4px;">
          <span style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;padding:2px 8px;border-radius:99px;font-size:.78rem;font-weight:600;">
            <i class="bi bi-shield-check" style="color:#059669;"></i> ${escapeHtml(lv.vaccinationStatus || 'None Recorded')}
          </span>
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Heads / Quantity</span>
        <div style="font-size:1.15rem;font-weight:800;color:#0f172a;margin-top:2px;">
          ${parseInt(lv.qty || 1).toLocaleString()} Head${parseInt(lv.qty || 1) !== 1 ? 's' : ''}
        </div>
      </div>
    </div>

    <!-- Animal Specifications -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:1.25rem;">
      <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-info-circle-fill" style="color:#059669;"></i> Animal Biological &amp; Physical Specifications
      </h4>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;font-size:.82rem;">
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Species / Animal Type</span>
          <strong style="color:#1e293b;">${escapeHtml(lv.type || 'N/A')}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Breed</span>
          <strong style="color:#1e293b;">${escapeHtml(lv.breed || 'Standard / Unspecified breed')}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Age (months)</span>
          <strong style="color:#1e293b;">${lv.age ? lv.age + ' months' : 'Not recorded'}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Weight (kg)</span>
          <strong style="color:#1e293b;">${lv.weight ? lv.weight + ' kg' : 'Not recorded'}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Latest Veterinary Checkup</span>
          <strong style="color:#1e293b;">${lv.latest_checkup ? new Date(lv.latest_checkup).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'}) : 'No checkups logged'}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Registration Date</span>
          <strong style="color:#1e293b;">${lv.createdAt ? new Date(lv.createdAt).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'}) : 'N/A'}</strong>
        </div>
        ${lv.notes ? `
        <div style="grid-column:1 / -1;background:#f8fafc;padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;">
          <span style="color:#64748b;display:block;font-size:.72rem;font-weight:700;">Inspection Remarks &amp; Physical Marks</span>
          <span style="color:#1e293b;">${escapeHtml(lv.notes)}</span>
        </div>` : ''}
      </div>
    </div>

    <!-- Farm Facility & Owner Information -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:1.25rem;">
      <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-geo-alt-fill" style="color:#059669;"></i> Farm Facility &amp; Owner Contact
      </h4>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;font-size:.82rem;">
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Farm Facility</span>
          <strong style="color:#1e293b;">${escapeHtml(lv.farm_name || 'N/A')}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Municipality / Location</span>
          <strong style="color:#1e293b;">${escapeHtml(lv.pet_municipality || 'Camarines Sur')}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Farm Owner</span>
          <strong style="color:#1e293b;">${escapeHtml([lv.firstName, lv.lastName].filter(Boolean).join(' ') || 'Farmer')}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Owner Contact</span>
          <strong style="color:#1e293b;"><i class="bi bi-telephone"></i> ${escapeHtml(lv.mobile || ' - ')}</strong>
        </div>
        ${lv.owner_email ? `
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Owner Email</span>
          <strong style="color:#1e293b;"><i class="bi bi-envelope"></i> ${escapeHtml(lv.owner_email)}</strong>
        </div>` : ''}
        ${lv.farm_address ? `
        <div style="grid-column:1 / -1;">
          <span style="color:#64748b;display:block;font-size:.72rem;">Farm Address</span>
          <span style="color:#1e293b;">${escapeHtml(lv.farm_address)}</span>
        </div>` : ''}
      </div>
    </div>

    <!-- Health History / Clinical Checkups -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;">
      <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-heart-pulse-fill" style="color:#059669;"></i> Clinical History &amp; Checkups (${healthRecs.length})
      </h4>
  `;

  if (healthRecs.length === 0) {
    html += `
      <div style="text-align:center;padding:1.25rem;background:#f8fafc;border-radius:10px;color:#64748b;font-size:.82rem;">
        No clinical or veterinary checkup records on file for this animal.
      </div>
    `;
  } else {
    html += `<div style="display:flex;flex-direction:column;gap:8px;">`;
    healthRecs.forEach(hr => {
      html += `
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;font-size:.82rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
            <strong style="color:#0f172a;">${escapeHtml(hr.checkup_type || 'Clinical Checkup')}</strong>
            <span style="color:#64748b;font-size:.74rem;">${hr.checkup_date ? new Date(hr.checkup_date).toLocaleDateString() : ''}</span>
          </div>
          <div style="color:#334155;margin-bottom:2px;"><b>Diagnosis / Finding:</b> ${escapeHtml(hr.diagnosis || 'Healthy')}</div>
          ${hr.treatment ? `<div style="color:#64748b;font-size:.76rem;"><b>Treatment:</b> ${escapeHtml(hr.treatment)}</div>` : ''}
          ${hr.veterinarian ? `<div style="color:#64748b;font-size:.74rem;margin-top:2px;">Attending Vet: ${escapeHtml(hr.veterinarian)}</div>` : ''}
        </div>
      `;
    });
    html += `</div>`;
  }
  html += `</div>`;

  if (body) body.innerHTML = html;
  const actions = document.getElementById('daLvDetActions');
  if (actions) {
    actions.innerHTML = `
      <button type="button" class="btn-cancel" onclick="closeDaLivestockDetailsModal()">Close</button>
      ${lv.farmId ? `<button type="button" class="btn btn-secondary" onclick="closeDaLivestockDetailsModal(); openFarmReview(${lv.farmId});"><i class="bi bi-flower1"></i> View Farm Registration</button>` : ''}
    `;
  }
  if (modal) modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeDaLivestockDetailsModal() {
  const modal = document.getElementById('daLivestockDetailsModal');
  if (modal) modal.classList.remove('active');
  document.body.style.overflow = '';
}

// -- VETERINARY REQUEST DETAILS MODAL --
function viewVetRequestDetails(requestId) {
  const vr = (typeof allVetRequestsData !== 'undefined' ? allVetRequestsData : []).find(x => parseInt(x.id) === parseInt(requestId));
  if (!vr) {
    alert('Veterinary request not found.');
    return;
  }
  const modal = document.getElementById('daVetRequestDetailsModal');
  const codeTitle = document.getElementById('daVrDetCodeTitle');
  const subTitle = document.getElementById('daVrDetSubtitle');
  const body = document.getElementById('daVrDetBody');
  const footerActions = document.getElementById('daVrDetFooterActions');

  if (codeTitle) codeTitle.textContent = vr.request_code || 'VET-REQUEST';
  if (subTitle) subTitle.textContent = `Submitted ${vr.created_at ? new Date(vr.created_at).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit'}) : 'N/A'}`;

  const uClr = vr.urgency === 'Emergency' ? '#ef4444' : (vr.urgency === 'Urgent' ? '#f59e0b' : '#10b981');
  const sClr = vr.status === 'Completed' ? '#10b981' : (vr.status === 'In Progress' ? '#3b82f6' : (vr.status === 'Assigned' ? '#8b5cf6' : '#d97706'));
  const farmerName = [vr.firstName, vr.lastName].filter(Boolean).join(' ') || 'Farmer';

  let html = `
    <!-- Top Status Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:1.25rem;">
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Urgency Level</span>
        <div style="margin-top:4px;">
          <span style="background:${uClr}15;color:${uClr};border:1px solid ${uClr}40;padding:2px 8px;border-radius:99px;font-size:.78rem;font-weight:700;">
            ${escapeHtml(vr.urgency || 'Normal')}
          </span>
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Request Status</span>
        <div style="margin-top:4px;">
          <span style="background:${sClr}15;color:${sClr};border:1px solid ${sClr}40;padding:2px 8px;border-radius:99px;font-size:.78rem;font-weight:700;">
            ${escapeHtml(vr.status || 'Pending')}
          </span>
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Animals Affected</span>
        <div style="font-size:1.15rem;font-weight:800;color:#0f172a;margin-top:2px;">
          ${parseInt(vr.animal_count || 1).toLocaleString()} ${escapeHtml(vr.livestock_type || 'Heads')}
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Preferred Schedule</span>
        <div style="font-size:1rem;font-weight:700;color:#2563eb;margin-top:2px;">
          ${vr.preferred_date ? new Date(vr.preferred_date).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'}) : 'ASAP'}
        </div>
      </div>
    </div>

    <!-- Farmer & Farm Details -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:1.25rem;">
      <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-person-fill" style="color:#059669;"></i> Farmer &amp; Farm Facility Information
      </h4>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;font-size:.82rem;">
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Farmer Name</span>
          <strong style="color:#1e293b;">${escapeHtml(farmerName)}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Phone Number</span>
          <strong style="color:#1e293b;"><i class="bi bi-telephone"></i> ${escapeHtml(vr.mobile || ' - ')}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Email Address</span>
          <strong style="color:#1e293b;"><i class="bi bi-envelope"></i> ${escapeHtml(vr.farmer_email || ' - ')}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Farm Facility</span>
          <strong style="color:#1e293b;">${escapeHtml(vr.farm_name || 'N/A')}</strong>
        </div>
        <div style="grid-column:1 / -1;">
          <span style="color:#64748b;display:block;font-size:.72rem;">Location / Address</span>
          <span style="color:#1e293b;font-weight:600;">${escapeHtml(vr.farm_address || vr.pet_municipality || 'Camarines Sur')}</span>
        </div>
      </div>
    </div>

    <!-- Request Details & Description -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:1.25rem;">
      <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-clipboard2-pulse-fill" style="color:#059669;"></i> Service Request &amp; Clinical Description
      </h4>
      <div style="margin-bottom:10px;font-size:.84rem;">
        <span style="color:#64748b;display:block;font-size:.72rem;">Request Category</span>
        <strong style="color:#0f172a;font-size:.92rem;">${escapeHtml(vr.request_type || 'Veterinary Assistance')}</strong>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;font-size:.84rem;line-height:1.45;color:#1e293b;">
        <span style="display:block;font-size:.72rem;font-weight:700;color:#64748b;margin-bottom:4px;text-transform:uppercase;">Farmer's Description &amp; Symptoms</span>
        ${vr.description ? escapeHtml(vr.description) : 'No additional symptoms specified.'}
      </div>
      ${vr.attachment_url ? `
      <div style="margin-top:10px;">
        <a href="${escapeHtml(vr.attachment_url)}" target="_blank" class="an-btn" style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;font-size:.78rem;display:inline-flex;align-items:center;gap:6px;">
          <i class="bi bi-paperclip"></i> View Attached Document / Photo
        </a>
      </div>` : ''}
    </div>

    <!-- Officer Assignment & Notes -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;">
      <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-shield-shaded" style="color:#059669;"></i> Officer Assignment &amp; Field Instructions
      </h4>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;font-size:.82rem;margin-bottom:10px;">
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Assigned Officer</span>
          <strong style="color:#1e293b;">${vr.officer_first ? escapeHtml([vr.officer_first, vr.officer_last].filter(Boolean).join(' ')) : 'Unassigned'}</strong>
        </div>
      </div>
      ${vr.officer_remarks ? `
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;font-size:.82rem;color:#334155;">
        <span style="display:block;font-size:.72rem;font-weight:700;color:#64748b;margin-bottom:2px;">Officer Remarks:</span>
        ${escapeHtml(vr.officer_remarks)}
      </div>` : '<div style="font-size:.78rem;color:#94a3b8;font-style:italic;">No officer remarks logged yet.</div>'}
    </div>
  `;

  if (body) body.innerHTML = html;

  let actionsHtml = `<button type="button" class="btn-cancel" onclick="closeDaVetRequestDetailsModal()">Close</button>`;
  if (vr.status === 'Pending') {
    actionsHtml += `<button type="button" class="btn btn-primary" onclick="closeDaVetRequestDetailsModal(); assignVetRequest(${vr.id}, 'Assigned')"><i class="bi bi-person-plus"></i> Assign to Me</button>`;
  } else if (vr.status === 'Assigned' || vr.status === 'In Progress') {
    actionsHtml += `<button type="button" class="btn btn-save" onclick="closeDaVetRequestDetailsModal(); assignVetRequest(${vr.id}, 'Completed')"><i class="bi bi-check2-circle"></i> Mark as Completed</button>`;
  }
  if (vr.farm_id) {
    actionsHtml += `<button type="button" class="btn btn-secondary" onclick="closeDaVetRequestDetailsModal(); openFarmReview(${vr.farm_id});"><i class="bi bi-flower1"></i> View Farm</button>`;
  }
  if (footerActions) footerActions.innerHTML = actionsHtml;

  if (modal) modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeDaVetRequestDetailsModal() {
  const modal = document.getElementById('daVetRequestDetailsModal');
  if (modal) modal.classList.remove('active');
  document.body.style.overflow = '';
}

// -- SLAUGHTER DETAILS MODAL --
function viewSlaughterDetails(slaughterId) {
  const sl = (typeof allSlaughterData !== 'undefined' ? allSlaughterData : []).find(x => parseInt(x.id) === parseInt(slaughterId));
  if (!sl) {
    alert('Slaughter declaration not found.');
    return;
  }
  const modal = document.getElementById('daSlaughterDetailsModal');
  const titleEl = document.getElementById('daSlDetTitle');
  const subEl = document.getElementById('daSlDetSubtitle');
  const body = document.getElementById('daSlDetBody');
  const footerActions = document.getElementById('daSlDetFooterActions');

  if (titleEl) titleEl.textContent = sl.declaration_no || 'Declaration Record';
  if (subEl) subEl.textContent = `Declared on ${sl.created_at ? new Date(sl.created_at).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'}) : 'N/A'}`;

  const sClr = sl.status === 'Approved' || sl.status === 'Verified' ? '#10b981' : (sl.status === 'Flagged/Investigating' ? '#ef4444' : '#d97706');
  const farmerName = [sl.firstName, sl.lastName].filter(Boolean).join(' ') || 'Farmer';

  let html = `
    <!-- Top Status Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:1.25rem;">
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Inspection Status</span>
        <div style="margin-top:4px;">
          <span style="background:${sClr}15;color:${sClr};border:1px solid ${sClr}40;padding:2px 8px;border-radius:99px;font-size:.78rem;font-weight:700;">
            ${escapeHtml(sl.status || 'Pending Review')}
          </span>
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Animal Ear Tag</span>
        <div style="font-size:1.1rem;font-weight:800;color:#0f172a;margin-top:2px;">
          <i class="bi bi-tag-fill" style="color:#059669;font-size:.85rem;"></i> ${escapeHtml(sl.animal_tag_id || 'N/A')}
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Species Type</span>
        <div style="font-size:1.1rem;font-weight:800;color:#2563eb;margin-top:2px;">
          ${escapeHtml(sl.livestock_type || 'N/A')}
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Slaughter Date</span>
        <div style="font-size:1rem;font-weight:700;color:#0f172a;margin-top:2px;">
          ${sl.slaughter_date ? new Date(sl.slaughter_date).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'}) : 'N/A'}
        </div>
      </div>
    </div>

    <!-- Slaughter Specifications -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:1.25rem;">
      <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-info-circle-fill" style="color:#059669;"></i> Slaughter Operation &amp; Facility Details
      </h4>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;font-size:.82rem;">
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Slaughter Category</span>
          <strong style="color:#1e293b;">${escapeHtml(sl.slaughter_type || 'Standard')}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Slaughter Location</span>
          <strong style="color:#1e293b;">${escapeHtml(sl.location || 'Declared location')}</strong>
        </div>
        <div style="grid-column:1 / -1;">
          <span style="color:#64748b;display:block;font-size:.72rem;">Purpose of Slaughter</span>
          <div style="background:#f8fafc;padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;color:#1e293b;margin-top:2px;">
            ${escapeHtml(sl.purpose || 'Household meat consumption')}
          </div>
        </div>
      </div>
    </div>

    <!-- Declarant Farmer & Farm Facility -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:1.25rem;">
      <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-person-fill" style="color:#059669;"></i> Declarant Farmer &amp; Farm Holding
      </h4>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;font-size:.82rem;">
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Farmer Name</span>
          <strong style="color:#1e293b;">${escapeHtml(farmerName)}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Contact Number</span>
          <strong style="color:#1e293b;"><i class="bi bi-telephone"></i> ${escapeHtml(sl.mobile || ' - ')}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Email Address</span>
          <strong style="color:#1e293b;"><i class="bi bi-envelope"></i> ${escapeHtml(sl.farmer_email || ' - ')}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Origin Farm Facility</span>
          <strong style="color:#1e293b;">${escapeHtml(sl.farm_name || 'N/A')}</strong>
        </div>
        ${sl.farm_address ? `
        <div style="grid-column:1 / -1;">
          <span style="color:#64748b;display:block;font-size:.72rem;">Farm Address</span>
          <span style="color:#1e293b;">${escapeHtml(sl.farm_address)}</span>
        </div>` : ''}
      </div>
    </div>

    <!-- Inspection & Compliance -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;">
      <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-shield-check" style="color:#059669;"></i> Ante-Mortem Inspection &amp; Certification
      </h4>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;font-size:.82rem;margin-bottom:10px;">
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Inspected By</span>
          <strong style="color:#1e293b;">${escapeHtml(sl.inspected_by || 'Municipal Meat Inspector')}</strong>
        </div>
        <div>
          <span style="color:#64748b;display:block;font-size:.72rem;">Supporting Document / Permit</span>
          ${sl.document_url ? `<a href="${escapeHtml(sl.document_url)}" target="_blank" class="an-btn" style="font-size:.75rem;padding:.2rem .6rem;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;"><i class="bi bi-file-earmark-pdf"></i> View Certificate</a>` : '<span style="color:#94a3b8;">No document attached</span>'}
        </div>
      </div>
      ${sl.inspector_notes ? `
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;font-size:.82rem;color:#334155;">
        <span style="display:block;font-size:.72rem;font-weight:700;color:#64748b;margin-bottom:2px;">Inspector Verification Notes:</span>
        ${escapeHtml(sl.inspector_notes)}
      </div>` : '<div style="font-size:.78rem;color:#94a3b8;font-style:italic;">No inspector verification notes logged.</div>'}
    </div>
  `;

  if (body) body.innerHTML = html;

  let actionsHtml = `<button type="button" class="btn-cancel" onclick="closeDaSlaughterDetailsModal()">Close</button>`;
  if (sl.status === 'Pending Review') {
    actionsHtml += `<button type="button" class="btn btn-save" onclick="closeDaSlaughterDetailsModal(); setSlaughterStatus(${sl.id}, 'Verified')"><i class="bi bi-check-lg"></i> Verify Declaration</button>`;
  }
  if (sl.farm_id) {
    actionsHtml += `<button type="button" class="btn btn-secondary" onclick="closeDaSlaughterDetailsModal(); openFarmReview(${sl.farm_id});"><i class="bi bi-flower1"></i> View Farm</button>`;
  }
  if (footerActions) footerActions.innerHTML = actionsHtml;

  if (modal) modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeDaSlaughterDetailsModal() {
  const modal = document.getElementById('daSlaughterDetailsModal');
  if (modal) modal.classList.remove('active');
  document.body.style.overflow = '';
}

// -- INVESTIGATE PUBLIC REPORT MODAL --
function investigatePublicReport(reportId) {
  const rpt = (typeof allPublicReportsData !== 'undefined' ? allPublicReportsData : []).find(x => parseInt(x.id) === parseInt(reportId));
  if (!rpt) {
    alert('Public report not found.');
    return;
  }
  const modal = document.getElementById('daInvestigateReportModal');
  const titleEl = document.getElementById('daRptDetTitle');
  const subEl = document.getElementById('daRptDetSubtitle');
  const body = document.getElementById('daRptDetBody');
  const footerActions = document.getElementById('daRptDetFooterActions');

  if (titleEl) titleEl.textContent = `Public Incident Report #${rpt.id}`;
  if (subEl) subEl.textContent = `Reported on ${rpt.createdAt ? new Date(rpt.createdAt).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit'}) : 'N/A'}`;

  const stColor = rpt.status === 'Resolved' ? '#10b981' : (rpt.status === 'Reviewed' ? '#3b82f6' : (rpt.status === 'Closed' ? '#64748b' : '#f59e0b'));

  let html = `
    <!-- Top Status Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:1.25rem;">
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Incident Category</span>
        <div style="margin-top:4px;">
          <span style="background:#ede9fe;color:#7c3aed;padding:2px 8px;border-radius:99px;font-size:.8rem;font-weight:700;">
            ${escapeHtml(rpt.reportType || 'Public')}${rpt.otherType ? ' (' + escapeHtml(rpt.otherType) + ')' : ''}
          </span>
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Investigation Status</span>
        <div style="margin-top:4px;">
          <span style="background:${stColor}15;color:${stColor};border:1px solid ${stColor}40;padding:2px 8px;border-radius:99px;font-size:.8rem;font-weight:700;">
            ${escapeHtml(rpt.status || 'Pending')}
          </span>
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Reporter Phone</span>
        <div style="font-size:.95rem;font-weight:700;color:#0f172a;margin-top:4px;">
          <i class="bi bi-telephone-fill" style="color:#059669;font-size:.8rem;"></i> ${escapeHtml(rpt.contactPhone || 'None')}
        </div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <span style="font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;">Reporter Email</span>
        <div style="font-size:.85rem;font-weight:600;color:#334155;margin-top:4px;overflow:hidden;text-overflow:ellipsis;">
          ${escapeHtml(rpt.contactEmail || 'Not provided')}
        </div>
      </div>
    </div>

    <!-- Description Narrative -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:1.25rem;">
      <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 8px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-card-text" style="color:#059669;"></i> Citizen Incident Description
      </h4>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;font-size:.86rem;line-height:1.5;color:#1e293b;">
        ${rpt.description ? escapeHtml(rpt.description) : 'No description provided.'}
      </div>
    </div>

    <!-- GPS & Location Details -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:1.25rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px;">
        <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:6px;">
          <i class="bi bi-geo-alt-fill" style="color:#059669;"></i> Incident Geolocation &amp; Coordinates
        </h4>
        ${rpt.latitude && rpt.longitude ? `
        <a href="https://www.google.com/maps?q=${rpt.latitude},${rpt.longitude}" target="_blank" class="an-btn" style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;font-size:.76rem;padding:.3rem .7rem;">
          <i class="bi bi-box-arrow-up-right"></i> Open in Google Maps
        </a>` : ''}
      </div>
      <div style="font-size:.84rem;color:#334155;">
        ${rpt.latitude && rpt.longitude ? `
          <div><b>Coordinates:</b> Latitude <code>${rpt.latitude}</code>, Longitude <code>${rpt.longitude}</code></div>
          <small style="color:#64748b;">GPS coordinates tagged during mobile citizen incident submission.</small>
        ` : `
          <div style="color:#94a3b8;font-style:italic;">No GPS coordinates captured for this report.</div>
        `}
      </div>
    </div>

    <!-- Evidence Photos / Media -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;">
      <h4 style="font-size:.88rem;font-weight:800;color:#0f172a;margin:0 0 10px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-images" style="color:#059669;"></i> Verification Photos &amp; Incident Evidence
      </h4>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
        <!-- Evidence Media -->
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px;text-align:center;">
          <span style="display:block;font-size:.74rem;font-weight:700;color:#475569;margin-bottom:6px;">Incident Photo</span>
          ${rpt.mediaUrl ? `
            <a href="${escapeHtml(rpt.mediaUrl)}" target="_blank">
              <img src="${escapeHtml(rpt.mediaUrl)}" style="max-width:100%;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid #cbd5e1;" onerror="this.outerHTML='<p style=\\'color:#94a3b8;font-size:.75rem;\\'>Image unavailable</p>'">
            </a>
          ` : '<p style="color:#94a3b8;font-size:.75rem;padding:20px 0;">No photo attached</p>'}
        </div>
        <!-- Valid ID -->
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px;text-align:center;">
          <span style="display:block;font-size:.74rem;font-weight:700;color:#475569;margin-bottom:6px;">Citizen Valid ID</span>
          ${rpt.idPhotoUrl ? `
            <a href="${escapeHtml(rpt.idPhotoUrl)}" target="_blank">
              <img src="${escapeHtml(rpt.idPhotoUrl)}" style="max-width:100%;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid #cbd5e1;" onerror="this.outerHTML='<p style=\\'color:#94a3b8;font-size:.75rem;\\'>ID unavailable</p>'">
            </a>
          ` : '<p style="color:#94a3b8;font-size:.75rem;padding:20px 0;">No ID uploaded</p>'}
        </div>
        <!-- Face Selfie -->
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px;text-align:center;">
          <span style="display:block;font-size:.74rem;font-weight:700;color:#475569;margin-bottom:6px;">Citizen Verification Selfie</span>
          ${rpt.facePhotoUrl ? `
            <a href="${escapeHtml(rpt.facePhotoUrl)}" target="_blank">
              <img src="${escapeHtml(rpt.facePhotoUrl)}" style="max-width:100%;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid #cbd5e1;" onerror="this.outerHTML='<p style=\\'color:#94a3b8;font-size:.75rem;\\'>Selfie unavailable</p>'">
            </a>
          ` : '<p style="color:#94a3b8;font-size:.75rem;padding:20px 0;">No selfie uploaded</p>'}
        </div>
      </div>
    </div>
  `;

  if (body) body.innerHTML = html;

  let footHtml = `
    <button type="button" class="btn-cancel" onclick="closeDaInvestigateReportModal()">Close</button>
    <div style="display:inline-flex;align-items:center;gap:8px;">
      <span style="font-size:.78rem;font-weight:700;color:#475569;">Update Status:</span>
      <select onchange="updateInvestigateReportStatus(${rpt.id}, this.value)" style="font-size:.8rem;padding:.35rem .6rem;border:1px solid #cbd5e1;border-radius:6px;background:#fff;font-family:inherit;">
        <option value="Pending" ${rpt.status==='Pending'?'selected':''}>Pending</option>
        <option value="Reviewed" ${rpt.status==='Reviewed'?'selected':''}>Reviewed</option>
        <option value="Resolved" ${rpt.status==='Resolved'?'selected':''}>Resolved</option>
        <option value="Closed" ${rpt.status==='Closed'?'selected':''}>Closed</option>
      </select>
    </div>
  `;
  if (footerActions) footerActions.innerHTML = footHtml;

  if (modal) modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeDaInvestigateReportModal() {
  const modal = document.getElementById('daInvestigateReportModal');
  if (modal) modal.classList.remove('active');
  document.body.style.overflow = '';
}

// -- INCIDENT DETAILS REVIEW MODAL --
function viewIncidentDetails(incidentId) {
  const inc = (typeof allIncidentsData !== 'undefined' ? allIncidentsData : (typeof incidentsData !== 'undefined' ? incidentsData : [])).find(x => parseInt(x.id) === parseInt(incidentId));
  if (!inc) {
    alert('Incident record not found.');
    return;
  }
  const modal = document.getElementById('daIncidentDetailsModal');
  const titleEl = document.getElementById('daIncDetTitle');
  const body = document.getElementById('daIncDetBody');
  const actions = document.getElementById('daIncDetActions');

  if (titleEl) titleEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill" style="color:#d97706;"></i> Incident Review &bull; #${inc.id}`;

  const prioClr = inc.priority === 'Critical' ? '#dc2626' : (inc.priority === 'High' ? '#c2410c' : (inc.priority === 'Medium' ? '#d97706' : '#16a34a'));
  const stClr = inc.status === 'Resolved' || inc.status === 'Closed' ? '#059669' : (inc.status === 'In Progress' ? '#2563eb' : '#d97706');
  const reporterName = [inc.firstName, inc.lastName].filter(Boolean).join(' ') || (inc.reporter_name || 'DA Staff / Informant');

  let html = `
    <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:12px;">
      <div style="width:36px;height:36px;border-radius:50%;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">
        <i class="bi bi-shield-exclamation"></i>
      </div>
      <div style="font-size:0.85rem;color:#92400e;line-height:1.45;">
        <strong style="font-size:0.92rem;display:block;margin-bottom:2px;">Biosecurity &amp; Health Incident #${inc.id}</strong>
        ${escapeHtml(inc.title || 'Incident Report')} &bull; Reported on ${inc.createdAt ? new Date(inc.createdAt).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'}) : 'N/A'}
      </div>
    </div>

    <div class="review-section">
      <h4><i class="bi bi-info-circle-fill"></i> Incident Specifications</h4>
      <div class="review-grid">
        <div class="review-item"><div class="r-label">Incident Title</div><div class="r-value">${escapeHtml(inc.title || 'N/A')}</div></div>
        <div class="review-item"><div class="r-label">Incident Type</div><div class="r-value">${escapeHtml(inc.type || 'N/A')}</div></div>
        <div class="review-item"><div class="r-label">Priority Level</div><div class="r-value">
          <span style="background:${prioClr}15;color:${prioClr};border:1px solid ${prioClr}40;padding:2px 8px;border-radius:99px;font-size:.78rem;font-weight:700;">
            ${escapeHtml(inc.priority || 'Normal')}
          </span>
        </div></div>
        <div class="review-item"><div class="r-label">Current Status</div><div class="r-value">
          <span style="background:${stClr}15;color:${stClr};border:1px solid ${stClr}40;padding:2px 8px;border-radius:99px;font-size:.78rem;font-weight:700;">
            ${escapeHtml(inc.status || 'Active')}
          </span>
        </div></div>
        <div class="review-item"><div class="r-label">Date Reported</div><div class="r-value">${inc.createdAt ? new Date(inc.createdAt).toLocaleDateString() : 'N/A'}</div></div>
        <div class="review-item"><div class="r-label">Date Resolved</div><div class="r-value">${inc.resolvedAt ? new Date(inc.resolvedAt).toLocaleDateString() : 'Unresolved'}</div></div>
      </div>
    </div>

    <div class="review-section">
      <h4><i class="bi bi-card-text"></i> Incident Narrative &amp; Clinical Symptoms</h4>
      <div style="background:var(--c-slate-50);padding:12px;border-radius:8px;font-size:.85rem;color:var(--c-text-main);line-height:1.5;">
        ${inc.description ? escapeHtml(inc.description) : 'No narrative description recorded.'}
      </div>
    </div>

    <div class="review-section">
      <h4><i class="bi bi-house-door-fill"></i> Affected Farm &amp; Reporter</h4>
      <div class="review-grid">
        <div class="review-item"><div class="r-label">Farm Facility</div><div class="r-value">${escapeHtml(inc.farm_name || 'N/A')}</div></div>
        <div class="review-item"><div class="r-label">Location / Municipality</div><div class="r-value">${escapeHtml(inc.pet_municipality || 'Camarines Sur')}</div></div>
        <div class="review-item"><div class="r-label">Reported By</div><div class="r-value">${escapeHtml(reporterName)}</div></div>
      </div>
    </div>

    ${inc.farm_lat && inc.farm_lng ? `
    <div class="review-section">
      <h4><i class="bi bi-geo-alt-fill"></i> GPS Location</h4>
      <p style="font-size:0.85rem;color:#374151;font-weight:600;"><i class="bi bi-geo-alt-fill"></i> ${inc.farm_lat}, ${inc.farm_lng}
        <a href="https://maps.google.com/?q=${inc.farm_lat},${inc.farm_lng}" target="_blank" rel="noopener" style="color:var(--c-brand-accent);margin-left:8px;">Open in Maps <i class="bi bi-arrow-up-right"></i></a>
      </p>
    </div>` : ''}
  `;

  if (body) body.innerHTML = html;

  let actHtml = `<button type="button" class="btn-cancel" onclick="closeDaIncidentDetailsModal()">Close</button>`;
  if (inc.status !== 'Resolved' && inc.status !== 'Closed') {
    actHtml += `<button type="button" class="btn btn-primary" onclick="resolveAndArchive(${inc.id}, null); closeDaIncidentDetailsModal();"><i class="bi bi-check2"></i> Resolve Incident</button>`;
  }
  if (inc.farmId) {
    actHtml += `<button type="button" class="btn btn-secondary" onclick="closeDaIncidentDetailsModal(); openFarmReview(${inc.farmId});"><i class="bi bi-flower1"></i> View Farm</button>`;
  }
  if (actions) actions.innerHTML = actHtml;

  if (modal) modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeDaIncidentDetailsModal() {
  const modal = document.getElementById('daIncidentDetailsModal');
  if (modal) modal.classList.remove('active');
  document.body.style.overflow = '';
}

// Global modal close on Escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    if (typeof closeFarmReview === 'function') closeFarmReview();
    if (typeof closeDaLivestockDetailsModal === 'function') closeDaLivestockDetailsModal();
    if (typeof closeDaVetRequestDetailsModal === 'function') closeDaVetRequestDetailsModal();
    if (typeof closeDaSlaughterDetailsModal === 'function') closeDaSlaughterDetailsModal();
    if (typeof closeDaInvestigateReportModal === 'function') closeDaInvestigateReportModal();
    if (typeof closeDaIncidentDetailsModal === 'function') closeDaIncidentDetailsModal();
  }
});

async function updateInvestigateReportStatus(reportId, newStatus) {
  if (typeof showLoading === 'function') showLoading(true);
  try {
    const res = await fetch(window.location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=update_report_priority&report_id=${reportId}&status=${encodeURIComponent(newStatus)}`
    });
    const r = await res.json();
    if (r.success) {
      if (typeof showToast === 'function') {
        showToast(`Report #${reportId} status updated to ${newStatus}`, 'success');
      } else {
        alert(`Status updated to ${newStatus}`);
      }
      setTimeout(() => location.reload(), 1000);
    } else {
      alert('Error updating status: ' + (r.message || 'Failed'));
    }
  } catch (err) {
    alert('Network error updating report status.');
  } finally {
    if (typeof showLoading === 'function') showLoading(false);
  }
}

function setFarmerStatus(userId, status) {
  if (!confirm(`Are you sure you want to set this farmer account to ${status}?`)) return;
  const fd = new FormData();
  fd.append('action', status === 'Active' ? 'approve_farmer' : 'deactivate_farmer');
  fd.append('user_id', userId);

  fetch('agriDA.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        showSuccessModal(res.message || 'Farmer account updated successfully!');
        setTimeout(() => location.reload(), 1200);
      } else {
        alert('Error: ' + (res.message || 'Failed to update farmer account.'));
      }
    })
    .catch(err => alert('Network error updating account.'));
}

function assignVetRequest(reqId, status) {
  const remarks = prompt('Enter officer remarks or instructions (optional):', status === 'Completed' ? 'Field checkup performed and recorded.' : 'Assigned to municipal veterinary response team.');
  if (remarks === null) return;

  const fd = new FormData();
  fd.append('action', 'assign_vet_request');
  fd.append('request_id', reqId);
  fd.append('status', status);
  fd.append('remarks', remarks);

  fetch('agriDA.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        showSuccessModal(res.message || 'Veterinary request updated!');
        setTimeout(() => location.reload(), 1200);
      } else {
        alert('Error: ' + (res.message || 'Failed to update request.'));
      }
    })
    .catch(err => alert('Network error updating assistance request.'));
}

function setSlaughterStatus(slgId, status) {
  const notes = prompt('Enter inspector verification notes:', 'Inspected and certified compliant with NMIS / DA biosecurity standards.');
  if (notes === null) return;

  const fd = new FormData();
  fd.append('action', 'update_slaughter_status');
  fd.append('declaration_id', slgId);
  fd.append('status', status);
  fd.append('notes', notes);

  fetch('agriDA.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        showSuccessModal(res.message || 'Slaughter declaration updated!');
        setTimeout(() => location.reload(), 1200);
      } else {
        alert('Error: ' + (res.message || 'Failed to update slaughter status.'));
      }
    })
    .catch(err => alert('Network error updating slaughter status.'));
}

function setInsuranceStatus(insId, status) {
  const code = prompt('Enter PCIC Endorsement / Tracking Reference Code:', 'PCIC-R05-CAM-' + Math.floor(1000 + Math.random() * 9000));
  if (code === null) return;

  const fd = new FormData();
  fd.append('action', 'update_insurance_status');
  fd.append('insurance_id', insId);
  fd.append('status', status);
  fd.append('tracking_code', code);

  fetch('agriDA.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        showSuccessModal(res.message || 'PCIC insurance status updated!');
        setTimeout(() => location.reload(), 1200);
      } else {
        alert('Error: ' + (res.message || 'Failed to update insurance status.'));
      }
    })
    .catch(err => alert('Network error updating insurance referral.'));
}

function deleteSlaughterDeclaration(id) {
  if (!confirm('Are you sure you want to permanently delete this slaughter declaration?')) return;
  const fd = new FormData();
  fd.append('action', 'delete_slaughter_declaration');
  fd.append('id', id);
  fd.append('csrf_token', CSRF_TOKEN);
  fetch('agriDA.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        showSuccessModal(res.message || 'Slaughter declaration deleted successfully.');
        setTimeout(() => location.reload(), 900);
      } else {
        alert('Error: ' + (res.message || 'Failed to delete slaughter declaration.'));
      }
    })
    .catch(err => alert('Network error deleting slaughter declaration.'));
}

function deleteHealthRecord(id) {
  if (!confirm('Are you sure you want to permanently delete this animal health record?')) return;
  const fd = new FormData();
  fd.append('action', 'delete_health_record');
  fd.append('id', id);
  fd.append('csrf_token', CSRF_TOKEN);
  fetch('agriDA.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        showSuccessModal(res.message || 'Animal health record deleted successfully.');
        setTimeout(() => location.reload(), 900);
      } else {
        alert('Error: ' + (res.message || 'Failed to delete health record.'));
      }
    })
    .catch(err => alert('Network error deleting health record.'));
}

function deleteVetRequest(id) {
  if (!confirm('Are you sure you want to permanently delete this veterinary request?')) return;
  const fd = new FormData();
  fd.append('action', 'delete_vet_request');
  fd.append('id', id);
  fd.append('csrf_token', CSRF_TOKEN);
  fetch('agriDA.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        showSuccessModal(res.message || 'Veterinary request deleted successfully.');
        setTimeout(() => location.reload(), 900);
      } else {
        alert('Error: ' + (res.message || 'Failed to delete veterinary request.'));
      }
    })
    .catch(err => alert('Network error deleting veterinary request.'));
}


// -- FORM REVIEW SYSTEM -------------------------------------------------------------
let activeReviewTab = 'freg';

function switchReviewTab(tab) {
  activeReviewTab = tab;
  document.querySelectorAll('.da-review-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('tabBtn-' + tab)?.classList.add('active');
  document.querySelectorAll('.review-pane').forEach(p => p.style.display = 'none');
  const targetPane = document.getElementById('pane-' + tab);
  if (targetPane) targetPane.style.display = 'block';
  filterReviewSubmissions();
}

function filterReviewSubmissions() {
  const query = (document.getElementById('reviewSearchInput')?.value || '').toLowerCase().trim();
  const status = document.getElementById('reviewStatusFilter')?.value || 'all';
  const pane = document.getElementById('pane-' + activeReviewTab);
  if (!pane) return;

  pane.querySelectorAll('.review-row').forEach(row => {
    const searchMatch = !query || (row.dataset.search || '').includes(query);
    const rowStatus = row.dataset.status || '';
    const statusMatch = (status === 'all') || (status === 'pending' && rowStatus === 'pending') || (rowStatus === status);
    row.style.display = (searchMatch && statusMatch) ? '' : 'none';
  });
}

function acceptSubmission(type, id, farmerName) {
  if (!confirm('Are you sure you want to ACCEPT the ' + type.replace('_', ' ') + ' submission from ' + farmerName + '?')) return;

  const fd = new FormData();
  fd.append('action', 'review_form_submission');
  fd.append('form_type', type);
  fd.append('submission_id', id);
  fd.append('decision', 'accepted');
  fd.append('csrf_token', CSRF_TOKEN);

  fetch('agriDA.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showSuccessModal(data.message || 'Submission accepted!');
        setTimeout(() => location.reload(), 1200);
      } else {
        alert('Action failed: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(err => alert('Error: ' + err.message));
}

function openDeclineModal(type, id, farmerName) {
  document.getElementById('declineFormType').value = type;
  document.getElementById('declineSubmissionId').value = id;
  document.getElementById('declineFarmerName').textContent = farmerName;
  document.getElementById('declineRemarks').value = '';
  const modal = document.getElementById('daDeclineModal');
  if (modal) modal.style.display = 'flex';
}

function closeDaDeclineModal() {
  const modal = document.getElementById('daDeclineModal');
  if (modal) modal.style.display = 'none';
}

function submitDeclineReview() {
  const type = document.getElementById('declineFormType').value;
  const id = document.getElementById('declineSubmissionId').value;
  const remarks = document.getElementById('declineRemarks').value.trim();
  if (!remarks) {
    alert('Please enter official remarks / reason for declining.');
    return;
  }
  const btn = document.getElementById('btnConfirmDecline');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...'; }

  const fd = new FormData();
  fd.append('action', 'review_form_submission');
  fd.append('form_type', type);
  fd.append('submission_id', id);
  fd.append('decision', 'declined');
  fd.append('remarks', remarks);
  fd.append('csrf_token', CSRF_TOKEN);

  fetch('agriDA.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showSuccessModal(data.message || 'Submission declined.');
        closeDaDeclineModal();
        setTimeout(() => location.reload(), 1200);
      } else {
        alert('Action failed: ' + (data.message || 'Unknown error'));
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-x-octagon-fill"></i> Confirm Decline'; }
      }
    })
    .catch(err => {
      alert('Error: ' + err.message);
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-x-octagon-fill"></i> Confirm Decline'; }
    });
}

// ===================== OFFICIAL FORM VIEW / PRINT / PDF =====================
const foV   = x => (x === undefined || x === null || String(x).trim() === '') ? '' : escapeHtml(x);
const foOn  = x => x === 1 || x === true || x === '1' || x === 'Yes' || x === 'yes' || x === 'on';
const foBox = (on, label) => `<span class="fo-chk"><i class="fo-box">${on ? '&#10003;' : ''}</i>${label}</span>`;
const foCell = (label, value, flex = 1, bg = true) =>
  `<div class="fo-cell ${bg ? 'fo-bg' : ''}" style="flex:${flex}"><span class="fo-lbl">${label}</span><span class="fo-val">${foV(value) || '&nbsp;'}</span></div>`;
const foDate = s => (s || '').toString().slice(0, 10);

function foStatusColor(st) {
  st = (st || 'pending').toLowerCase();
  return st === 'accepted' ? '#065f46' : (st === 'declined' ? '#991b1b' : '#b45309');
}

function foReviewBlock(sub, title) {
  const st = (sub.status || 'pending').toLowerCase();
  const c = foStatusColor(st);
  return `
  <div class="fo-sec blue">${title}</div>
  <div style="border:1px solid #cbd5e1;border-top:none;background:#f8fafc;padding:4px 6px;font-size:7.5pt;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <span>Review Status: <span class="fo-stamp" style="color:${c};border-color:${c};">${foV(st)}</span></span>
    <span><b>Reviewed By:</b> ${foV(sub.reviewer_name) || 'Pending DA review'}</span>
    <span><b>Date:</b> ${foV(foDate(sub.reviewed_at)) || '-'}</span>
    ${sub.remarks ? `<span style="flex-basis:100%;color:#991b1b;"><b>Remarks / Reason:</b> ${foV(sub.remarks)}</span>` : ''}
  </div>`;
}

/* ---------------- RSBSA ---------------- */
function buildReadOnlyRsbsaHtml(sub, f) {
  const name = sub.farmer_name || sub.applicant_name || 'Farmer';
  const isNew = (f.enrollment_type || 'New') === 'New';
  const addr = [f.pet_house_no, f.pet_street, f.pet_barangay || sub.barangay, f.pet_municipality || sub.municipality, f.pet_province || 'Camarines Sur'].filter(Boolean).join(', ');
  const parcels = [1, 2, 3].map(i => `
    <tr>
      <td style="text-align:center;font-weight:700;">Parcel ${i}</td>
      <td>${foV(f['parcel_loc_' + i]) || '&nbsp;'}</td>
      <td style="text-align:center;">${foV(f['parcel_area_' + i]) ? foV(f['parcel_area_' + i]) + ' ha' : ''}</td>
      <td>${foV(f['parcel_owner_' + i])}</td>
      <td>${foV(f['parcel_crops_' + i])}</td>
    </tr>`).join('');

  const livCol = (title, on, items) => `
    <div style="flex:1;border-right:1px solid #000;padding:2px 4px;font-size:7pt;">
      ${foBox(on, '<b>' + title + '</b>')}
      <div style="display:flex;flex-direction:column;gap:1px;margin:2px 0 0 12px;font-size:6.8pt;">${items}</div>
    </div>`;

  return `
  <div class="fo-sheet folio-read-sheet" id="readFolioRsbsa">
    <div class="fo-hdr" style="border-bottom:2px solid #008751;">
      <div style="display:flex;gap:8px;align-items:center;">
        <div class="fo-logo" style="color:#008751;border-color:#008751;">DA</div>
        <div>
          <div style="font-size:7.5pt;font-weight:700;text-transform:uppercase;">Republic of the Philippines &bull; Department of Agriculture</div>
          <div style="font-size:10.5pt;font-weight:900;color:#008751;line-height:1.1;">REGISTRY SYSTEM FOR BASIC SECTORS IN AGRICULTURE (RSBSA)</div>
          <div style="font-size:7pt;font-weight:700;color:#444;">ENROLLMENT FORM &bull; Revised Form No. C1-2020</div>
        </div>
      </div>
      <div style="width:62px;height:62px;border:1px dashed #111;background:#FDECD2;display:flex;align-items:center;justify-content:center;font-size:7pt;font-weight:800;color:#555;text-align:center;">2&times;2<br>PHOTO</div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;background:#f8fafc;border:1px solid #000;border-top:none;padding:2px 6px;font-size:7.5pt;">
      <div>${foBox(isNew, 'New Enrollment')} ${foBox(!isNew, 'Existing / Updating')}</div>
      <div><b>REFERENCE NO:</b> <span style="border:1px solid #000;background:#FDECD2;padding:1px 6px;font-weight:800;">${foV(sub.reference_no || f.reference_no) || 'N/A'}</span></div>
    </div>

    <div class="fo-banner">Part 1: Personal Information</div>
    <div class="fo-row">${foCell('1.1 Surname', f.surname, 2.2)}${foCell('First Name', f.first_name, 2.2)}${foCell('Middle Name', f.middle_name, 2)}${foCell('Ext.', f.ext_name, 1)}</div>
    <div class="fo-row">${foCell('1.2 Sex', f.pet_sex, 1.2)}${foCell('1.3 Civil Status', f.pet_civil_status, 1.8)}${foCell('1.4 Spouse', f.pet_spouse, 2.2)}${foCell("1.5 Mother's Maiden Name", f.pet_mothers_name, 2.6)}</div>
    <div class="fo-row">${foCell('1.7 Date of Birth', f.pet_dob, 1.4)}${foCell('Place of Birth', f.pet_pob, 1.8)}${foCell('Contact No.', f.contact_phone || sub.farmer_mobile, 1.6)}
      <div class="fo-cell fo-bg" style="flex:2.2"><span class="fo-lbl">1.6 Household Head?</span><span class="fo-val">${foBox(f.hh_head === 'Yes', 'Yes')}${foBox(f.hh_head === 'No', 'No')} ${foV(f.hh_head_name)}</span></div></div>
    <div class="fo-row">${foCell('1.8 Permanent Address', addr, 1)}</div>
    <div class="fo-row">${foCell('1.9 Highest Education', f.pet_education, 2)}
      <div class="fo-cell fo-bg" style="flex:3"><span class="fo-lbl">Special Sectors</span><span class="fo-val">${foBox(foOn(f.pwd), 'PWD')}${foBox(foOn(f.fourps), '4Ps')}${foBox(foOn(f.indigenous), 'Indigenous' + (f.indigenous_specify ? ' (' + foV(f.indigenous_specify) + ')' : ''))}</span></div>
      ${foCell('1.13 Gov\'t ID No.', f.gov_id_number, 2)}</div>
    <div class="fo-row">
      <div class="fo-cell fo-bg" style="flex:3"><span class="fo-lbl">Member of Farmers Assoc. / Cooperative?</span><span class="fo-val">${foBox(f.farmers_assoc === 'Yes', 'Yes')}${foBox(f.farmers_assoc !== 'Yes', 'No')} ${foV(f.farmers_assoc_name)}</span></div>
      ${foCell('Emergency Contact Person', f.emergency_contact_name, 2.5)}${foCell('Emergency Contact No.', f.emergency_contact_no, 2)}</div>

    <div class="fo-banner">Part 2: Farm Profile &amp; Livelihood Commodities</div>
    <div style="display:flex;border:1px solid #000;background:#FDECD2;margin-top:2px;">
      ${livCol('2.1 Farmer', foOn(f.farming_farmer),
        foBox(foOn(f.farming_rice), 'Rice') + foBox(foOn(f.farming_corn), 'Corn') +
        foBox(foOn(f.farming_livestock), 'Livestock' + (f.livestock_specify ? ': ' + foV(f.livestock_specify) : '')) +
        foBox(foOn(f.farming_poultry), 'Poultry' + (f.poultry_specify ? ': ' + foV(f.poultry_specify) : '')))}
      ${livCol('2.2 Farmworker / Laborer', foOn(f.farming_worker),
        foBox(foOn(f.work_landprep), 'Land Preparation') + foBox(foOn(f.work_planting), 'Planting / Transplanting') +
        foBox(foOn(f.work_cultivation), 'Cultivation / Weeding') + foBox(foOn(f.work_harvest), 'Harvesting / Threshing'))}
      ${livCol('2.3 Fisherfolk', foOn(f.farming_fisher),
        foBox(foOn(f.fish_capture), 'Fish Capture') + foBox(foOn(f.fish_culture), 'Aquaculture') +
        foBox(foOn(f.fish_glean), 'Gleaning') + foBox(foOn(f.fish_process), 'Processing / Vending'))}
      <div style="flex:1;padding:2px 4px;font-size:7pt;">
        ${foBox(foOn(f.farming_youth), '<b>2.4 Agri Youth</b>')}
        <div style="display:flex;flex-direction:column;gap:1px;margin:2px 0 0 12px;font-size:6.8pt;">
          ${foBox(foOn(f.youth_hh), 'Farming Household') + foBox(foOn(f.youth_course), 'Agri Course / Scholar') + foBox(foOn(f.youth_program), 'Agri Youth Program')}
        </div>
      </div>
    </div>
    <div class="fo-row">${foCell('Gross Annual Farming Income (PHP)', f.income_farming)}${foCell('Gross Annual Non-Farming Income (PHP)', f.income_non_farming)}${foCell('Total Farm Parcels', f.parcel_count)}</div>

    <div style="font-size:7pt;font-weight:800;text-transform:uppercase;margin:4px 0 1px;">2.8 Farm Parcel Details</div>
    <table class="fo-tbl">
      <thead><tr><th style="width:10%">Parcel #</th><th style="width:30%">Location</th><th style="width:12%">Area</th><th style="width:20%">Ownership</th><th style="width:28%">Commodities / Animals</th></tr></thead>
      <tbody>${parcels}</tbody>
    </table>

    <div class="fo-banner">Part 3: Declaration &amp; Data Privacy Consent</div>
    <div class="fo-note">I hereby declare that all information indicated in this application is true, correct and complete to the best of my knowledge. I understand that any false statement or material omission shall be ground for disqualification and forfeiture of government agricultural assistance. I consent to the processing of my personal data under R.A. 10173 (Data Privacy Act of 2012).</div>
    <div style="display:flex;border:1px solid #000;border-top:none;">
      <div style="flex:2;padding:3px 6px;border-right:1px solid #000;">
        <div class="fo-sig">${foV(name)}</div>
        <div style="font-size:6pt;font-weight:800;text-align:center;">SIGNATURE OF APPLICANT OVER PRINTED NAME</div>
        <div style="font-size:7pt;text-align:center;margin-top:2px;">Date Filed: <b>${foV(foDate(sub.created_at))}</b></div>
      </div>
      <div style="width:75px;padding:3px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center;">
        <div style="width:44px;height:50px;border:1px dashed #000;display:flex;align-items:center;justify-content:center;font-size:6.5pt;font-weight:700;color:#666;">THUMB</div>
      </div>
      <div style="flex:3;padding:3px 6px;display:flex;flex-direction:column;justify-content:flex-end;">
        <div class="fo-sig" style="padding-top:14px;">${foV(sub.reviewer_name) || '&nbsp;'}</div>
        <div style="font-size:6.5pt;font-weight:800;text-align:center;">VERIFIED BY: DA AGRICULTURAL OFFICER</div>
      </div>
    </div>

    ${foReviewBlock(sub, 'For DA Use Only &mdash; Verification Status')}
    <div class="fo-foot"><span>DA-RSBSA Form C1-2020 &bull; Official Registry Copy</span><span>Folio 8.5" &times; 13"</span></div>
  </div>`;
}

/* ---------------- PCIC ---------------- */
function buildReadOnlyPcicHtml(sub, f) {
  const name = sub.farmer_name || sub.applicant_name || 'Farmer';
  const appNew = (f.application_type || 'New') === 'New';
  const self = (f.farmer_category || 'Self-Financed') === 'Self-Financed';
  const animal = f.animal_type || sub.animal_type || '';
  const animals = ['Cattle', 'Carabao', 'Swine', 'Goat/Sheep', 'Poultry', 'Horse/Others'];
  const rows = [1, 2, 3, 4, 5, 6].map(i => `
    <tr>
      <td style="font-weight:700;">${foV(f['animal_tag_' + i]) || '&nbsp;'}</td>
      <td style="text-align:center;">${foV(f['animal_m_' + i])}</td>
      <td style="text-align:center;">${foV(f['animal_f_' + i])}</td>
      <td>${foV(f['animal_age_' + i])}</td>
      <td>${foV(f['animal_dob_' + i])}</td>
      <td>${foV(f['animal_breed_' + i])}</td>
      <td>${foV(f['animal_color_' + i])}</td>
      <td style="text-align:right;">${f['animal_val_' + i] ? 'PhP ' + (parseFloat(f['animal_val_' + i]) || 0).toFixed(2) : ''}</td>
    </tr>`).join('');

  return `
  <div class="fo-sheet folio-read-sheet" id="readFolioPcic">
    <div class="fo-hdr" style="border-bottom:2px solid #1E7B3A;align-items:flex-start;">
      <div style="display:flex;gap:8px;align-items:center;">
        <div class="fo-logo" style="color:#1E7B3A;border-color:#1E7B3A;">PCIC</div>
        <div>
          <div style="font-size:7.5pt;text-transform:uppercase;color:#333;">Republic of the Philippines</div>
          <div style="font-size:11pt;font-weight:800;color:#1E7B3A;font-family:Georgia,serif;line-height:1.1;">PHILIPPINE CROP INSURANCE CORPORATION</div>
          <div style="font-size:8pt;color:#444;">Regional Office No. <b>${foV(f.pcic_reg_office) || 'V (Bicol Region)'}</b></div>
        </div>
      </div>
      <div style="text-align:right;font-size:8pt;font-weight:700;">
        <span style="background:#1E7B3A;color:#fff;padding:2px 6px;border-radius:3px;">LIV-UPI-1</span><br>
        <span style="font-size:7pt;font-weight:400;">Rev. 2025/Sep</span>
      </div>
    </div>

    <div style="text-align:center;margin:4px 0 3px;">
      <div style="font-size:10pt;font-weight:800;text-decoration:underline;">APPLICATION FOR LIVESTOCK MORTALITY INSURANCE</div>
      <div style="font-size:7pt;font-style:italic;color:#555;">Application No.: <b>${foV(sub.application_no) || 'N/A'}</b></div>
    </div>

    <table class="fo-tbl" style="margin-bottom:3px;">
      <tr>
        <td style="width:30%">${foBox(appNew, 'New')} ${foBox(!appNew, 'Renewal')}</td>
        <td style="width:45%"><b>FARMER CATEGORY:</b> ${foBox(self, 'Self-Financed')} ${foBox(!self, 'Borrowing')} ${self ? '' : '<br><b>Lender:</b> ' + foV(f.lender_name)}</td>
        <td style="width:25%"><b>DATE:</b> ${foV(f.application_date || foDate(sub.created_at))}</td>
      </tr>
    </table>

    <div class="fo-sec">Section A. Basic Applicant Information</div>
    <div class="fo-row" style="margin-top:0">${foCell('Last Name', f.last_name, 2, false)}${foCell('First Name', f.first_name, 2, false)}${foCell('Middle Name', f.middle_name, 2, false)}${foCell('Suffix', f.suffix, 1, false)}</div>
    <div class="fo-row">${foCell('Contact No.', f.contact_no || sub.farmer_mobile, 2, false)}${foCell('Date of Birth', f.dob, 2, false)}
      <div class="fo-cell" style="flex:1.6"><span class="fo-lbl">Sex</span><span class="fo-val">${foBox(f.sex === 'Male', 'M')}${foBox(f.sex === 'Female', 'F')}</span></div>
      <div class="fo-cell" style="flex:3"><span class="fo-lbl">Civil Status</span><span class="fo-val">${['Single', 'Married', 'Widow', 'Separated'].map(s => foBox((f.civil_status || '').toLowerCase().startsWith(s.toLowerCase().slice(0, 4)), s)).join('')}</span></div></div>
    <div class="fo-row">${foCell('Complete Address', f.applicant_address, 1, false)}</div>
    <div class="fo-row"><div class="fo-cell" style="flex:1"><span class="fo-lbl">Special Sector</span><span class="fo-val">${foBox(foOn(f.sector_pwd), 'PWD')}${foBox(foOn(f.sector_sc), 'Senior Citizen')}${foBox(foOn(f.sector_youth), 'Youth (18-30)')}${foBox(foOn(f.sector_ip), 'Indigenous People')}</span></div></div>
    <div class="fo-row">${foCell('A.8 Primary Beneficiary', f.bene_primary_name, 3, false)}${foCell('Relationship', f.bene_primary_rel, 1.5, false)}${foCell('Age', f.bene_primary_age, .7, false)}</div>
    <div class="fo-row">${foCell('Contingent Beneficiary / Guardian', f.bene_contingent_name, 3, false)}${foCell('Relationship', f.bene_contingent_rel, 2.2, false)}</div>

    <div class="fo-sec">Section B. Farm and Livestock Information</div>
    <div class="fo-row" style="margin-top:0">${foCell('B.1 Farm Address / Location', f.farm_location || sub.farm_location, 4, false)}
      <div class="fo-cell" style="flex:2"><span class="fo-lbl">Facility Type</span><span class="fo-val">${foBox(f.facility_type !== 'Commercial', 'Backyard')}${foBox(f.facility_type === 'Commercial', 'Commercial')}</span></div></div>
    <div class="fo-row"><div class="fo-cell" style="flex:1"><span class="fo-lbl">B.2 Primary Animal Type to Insure</span><span class="fo-val">${animals.map(a => foBox(animal === a, a)).join('')}</span></div></div>

    <div style="font-size:7.5pt;font-weight:700;margin:4px 0 1px;">B.3 Description of Animals to be Insured</div>
    <table class="fo-tbl light">
      <thead><tr><th>Tag / ID No.</th><th>M</th><th>F</th><th>Age / Group</th><th>Date of Birth</th><th>Breed</th><th>Color</th><th>Value (PhP)</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>
    <div style="font-size:6.5pt;font-style:italic;color:#555;margin-top:2px;">* For poultry, indicate building/coop unit; for large livestock, enter individual ear tags. Attach extra sheets if more than 6 entries.</div>

    <table class="fo-tbl" style="margin-top:3px;">
      <tr>
        <td><b>B.4 Total Heads:</b> ${foV(f.head_count || sub.head_count)}</td>
        <td><b>B.5 Date Purchased:</b> ${foV(f.purchase_date)}</td>
        <td><b>B.6 Stock Source:</b> ${foBox((f.stock_source || 'Island-Born') === 'Island-Born', 'Island-Born')}${foBox(f.stock_source === 'Imported', 'Imported')}</td>
        <td><b>B.7 Housing Units:</b> ${foV(f.housing_units)}</td>
        <td><b>B.8 Heads / Unit:</b> ${foV(f.heads_per_unit)}</td>
      </tr>
    </table>

    <div class="fo-sec">Section C. Certification &amp; Consent</div>
    <div style="display:flex;border:1px solid #111;border-top:none;padding:4px;align-items:center;gap:8px;">
      <div style="flex:2.2;font-size:7pt;line-height:1.3;">
        ${foBox(foOn(f.cert_agree_1), 'I certify that all statements in this application are true, complete and correct.')}<br>
        ${foBox(foOn(f.cert_agree_2), 'I consent to the processing of my data under R.A. 10173 (Data Privacy Act of 2012).')}<br>
        ${foBox(foOn(f.cert_agree_3), 'I assign policy indemnity rights to the lending institution / designated program, if applicable.')}
      </div>
      <div style="flex:1;border-left:1px dashed #aaa;padding-left:8px;text-align:center;">
        <div class="fo-sig">${foV(name)}</div>
        <div style="font-size:6.5pt;color:#555;">Applicant Signature over Printed Name</div>
      </div>
    </div>

    <div class="fo-sec blue">Section D. Coverage (For PCIC / DA Use Only)</div>
    <table class="fo-tbl" style="background:#f8fafc;">
      <tr><td><b>D.1 Sum Insured / Head:</b> Subject to DA / PCIC evaluation</td><td><b>D.2 Premium Source:</b> 100% subsidized (RSBSA-qualified)</td></tr>
      <tr><td colspan="2"><b>D.3 Extended Coverage:</b> Anthrax, FMD, ASF, Avian Influenza and Hemorrhagic Septicemia under standard subsidized guidelines.</td></tr>
    </table>
    ${foReviewBlock(sub, 'Office Use &mdash; Underwriting / Review Status')}
    <div class="fo-foot"><span>PCIC LIV-UPI-1 (Rev. 2025) &bull; Official Review Copy</span><span>Folio 8.5" &times; 13"</span></div>
  </div>`;
}

/* ---------------- FARM REGISTRATION ---------------- */
function buildReadOnlyFarmRegHtml(sub, f) {
  const name = sub.farmer_name || 'Farmer';
  const isRenewal = (f.app_type || '').toLowerCase() === 'renewal';
  const own = (f.ownership || 'Private');
  const lvRows = ['cattle', 'swine', 'poultry', 'goat', 'carabao', 'horse', 'sheep', 'rabbit']
    .filter(k => parseInt(f['lv_qty_' + k] || 0) > 0)
    .map(k => `<tr><td style="text-transform:capitalize;font-weight:700;">${k}</td><td>${foV(f['lv_tag_' + k])}</td><td>${foV(f['lv_breed_' + k])}</td><td style="text-align:center;">${foV(f['lv_qty_' + k])}</td></tr>`).join('')
    || '<tr><td colspan="4" style="text-align:center;color:#777;">No livestock declared</td></tr>';
  const addr = [f.pet_house_no, f.pet_street, f.pet_barangay, f.pet_municipality, f.pet_province].filter(Boolean).join(', ');

  return `
  <div class="fo-sheet folio-read-sheet" id="readFolioFarmReg">
    <div class="fo-hdr" style="border-bottom:2px solid #10B981;">
      <div style="display:flex;gap:8px;align-items:center;">
        <div class="fo-logo" style="color:#10B981;border-color:#10B981;">FARM</div>
        <div>
          <div style="font-size:7pt;text-transform:uppercase;">Republic of the Philippines &bull; Bureau of Animal Industry</div>
          <div style="font-size:11pt;font-weight:800;color:#047857;">OFFICIAL FARM REGISTRATION &amp; BIOSECURITY ENROLLMENT</div>
          <div style="font-size:7.5pt;color:#444;">AgriTrace+ Verified Farm Facility Record</div>
        </div>
      </div>
      <div style="text-align:right;font-size:8pt;"><span style="background:#10B981;color:#fff;padding:2px 6px;border-radius:3px;font-weight:700;">FREG-${sub.id}</span></div>
    </div>
    <div style="background:#f8fafc;border:1px solid #000;border-top:none;padding:2px 6px;font-size:7.5pt;">
      ${foBox(!isRenewal, 'New')} ${foBox(isRenewal, 'Renewal')} &nbsp;|&nbsp; ${foBox(own !== 'Govt', 'Private')} ${foBox(own === 'Govt', 'Government')}
    </div>

    <div class="fo-banner">I. Petitioner Information</div>
    <div class="fo-row">${foCell('Surname', f.pet_surname, 2)}${foCell('First Name', f.pet_firstname, 2)}${foCell('Middle Name', f.pet_middle, 2)}${foCell('Ext.', f.pet_ext, 1)}</div>
    <div class="fo-row">${foCell('Sex', f.pet_sex, 1)}${foCell('Civil Status', f.pet_civil_status, 1.4)}${foCell('Date of Birth', f.pet_dob, 1.4)}${foCell('Place of Birth', f.pet_pob, 1.6)}${foCell('Contact No.', f.pet_phone || sub.farmer_mobile, 1.6)}</div>
    <div class="fo-row">${foCell('Address', addr, 1)}</div>
    <div class="fo-row">${foCell("Mother's Maiden Name", f.pet_mothers_name, 2)}${foCell('Spouse', f.pet_spouse, 2)}${foCell('Education', f.pet_education, 1.4)}${foCell('Religion', f.pet_religion, 1.2)}</div>
    <div class="fo-row">${foCell('Household Members (M / F)', (f.pet_male_members || 0) + ' / ' + (f.pet_female_members || 0), 1.4)}
      <div class="fo-cell fo-bg" style="flex:3.6"><span class="fo-lbl">Sectors</span><span class="fo-val">${foBox(f.hh_head === 'Yes', 'Household Head')}${foBox(f.pwd === 'Yes', 'PWD')}${foBox(f.fourps === 'Yes', '4Ps')}${foBox(f.indigenous === 'Yes', 'Indigenous')}${foBox(f.has_gov_id === 'Yes', 'Gov\'t ID ' + foV(f.gov_id_number))}</span></div></div>
    <div class="fo-row">${foCell('Farmers Assoc. / Coop', f.farmers_assoc === 'Yes' ? f.farmers_assoc_name : 'None', 2.5)}${foCell('Emergency Contact', f.emergency_contact_name, 2)}${foCell('Emergency No.', f.emergency_contact_no, 1.5)}</div>

    <div class="fo-banner">II. Farm Profile &amp; Main Livelihood</div>
    <div class="fo-row">${foCell('Farm Name', f.farm_name || sub.farm_name, 3)}${foCell('Farm Type', f.farm_type, 1.2)}${foCell('Area (ha)', f.farm_area, 1)}</div>
    <div class="fo-row">${foCell('Farm Address', f.farm_address || sub.farm_address, 1)}</div>
    <div class="fo-row">${foCell('Business Type', f.business_type, 1.6)}${foCell('Main Livelihood', f.main_livelihood, 1.6)}
      <div class="fo-cell fo-bg" style="flex:3"><span class="fo-lbl">Farming Activity</span><span class="fo-val">${foBox(foOn(f.farming_rice), 'Rice')}${foBox(foOn(f.farming_corn), 'Corn')}${foBox(foOn(f.farming_livestock), 'Livestock')}${foBox(foOn(f.farming_poultry), 'Poultry')}${foBox(foOn(f.farming_other), 'Other')}</span></div></div>
    <div class="fo-row">${foCell('Income - Farming (PHP)', f.income_farming)}${foCell('Income - Non-farming (PHP)', f.income_non_farming)}${foCell('GPS', (f.farm_lat && f.farm_lng) ? f.farm_lat + ', ' + f.farm_lng : '', 1.4)}</div>

    <div class="fo-banner">III. Livestock Inventory</div>
    <table class="fo-tbl" style="margin-top:2px;">
      <thead><tr><th style="width:22%">Animal Type</th><th style="width:30%">Tag ID</th><th style="width:30%">Breed</th><th style="width:18%">No. of Heads</th></tr></thead>
      <tbody>${lvRows}</tbody>
    </table>

    <div class="fo-note" style="margin-top:6px;border-top:1px solid #000;">Registered farms are subject to periodic veterinary verification, livestock quarantine checks and vaccination compliance under municipal agriculture biosecurity regulations (RA 8485).</div>
    <div style="display:flex;justify-content:space-between;margin:10px 20px 0;gap:30px;">
      <div style="flex:1;text-align:center;"><div class="fo-sig">${foV(name)}</div><div style="font-size:6.5pt;">Farm Owner / Petitioner</div></div>
      <div style="flex:1;text-align:center;"><div class="fo-sig">${foV(sub.reviewer_name) || '&nbsp;'}</div><div style="font-size:6.5pt;">Municipal Agricultural Officer / Verifier</div></div>
    </div>
    ${foReviewBlock(sub, 'For DA Use Only &mdash; Verification Status')}
    <div class="fo-foot"><span>Submitted: ${foV(sub.created_at)}</span><span>Folio 8.5" &times; 13"</span></div>
  </div>`;
}

/* ---------------- Fetch + build helper ---------------- */
async function fetchSubmission(type, id) {
  const fd = new FormData();
  fd.append('action', 'get_form_submission');
  fd.append('form_type', type);
  fd.append('submission_id', id);
  const r = await fetch('agriDA.php', { method: 'POST', body: fd });
  const data = await r.json();
  if (!data.success || !data.submission) throw new Error(data.message || 'Submission not found');
  return data.submission;
}

function buildSubmissionHtml(type, sub) {
  const f = sub.parsed_form_data || {};
  if (type === 'pcic') return buildReadOnlyPcicHtml(sub, f);
  if (type === 'rsbsa') return buildReadOnlyRsbsaHtml(sub, f);
  return buildReadOnlyFarmRegHtml(sub, f);
}

function foFileName(type, farmerName, dateStr) {
  const n = (farmerName || 'Farmer').replace(/[^a-zA-Z0-9]/g, '_');
  const d = dateStr || new Date().toISOString().slice(0, 10).replace(/-/g, '');
  const p = type === 'pcic' ? 'PCIC_LIV-UPI-1_' : (type === 'rsbsa' ? 'RSBSA_Enrollment_' : 'Farm_Registration_');
  return p + n + '_' + d + '.pdf';
}

/* ---------------- View modal ---------------- */
let daCurrentView = { type: null, sub: null };

function closeDaViewModal() {
  const modal = document.getElementById('daViewFormModal');
  if (modal) modal.style.display = 'none';
  document.body.style.overflow = '';
}

function viewSubmissionForm(type, id) {
  fetchSubmission(type, id)
    .then(sub => renderReadOnlySubmission(type, sub))
    .catch(err => alert('Failed to load submission: ' + err.message));
}

function renderReadOnlySubmission(type, sub) {
  const modal = document.getElementById('daViewFormModal');
  const contentEl = document.getElementById('daViewModalContent');
  const titleEl = document.getElementById('daViewModalTitle');
  const subEl = document.getElementById('daViewModalSubtitle');
  const btnPdf = document.getElementById('btnDaDownloadPdf');
  const farmerName = sub.farmer_name || sub.applicant_name || 'Farmer';
  const st = (sub.status || 'Pending').toUpperCase();
  daCurrentView = { type, sub };

  if (type === 'pcic') {
    titleEl.textContent = 'PCIC Livestock Mortality Insurance Application';
    subEl.textContent = 'Applicant: ' + farmerName + ' | App #: ' + (sub.application_no || 'N/A') + ' | Status: ' + st;
  } else if (type === 'rsbsa') {
    titleEl.textContent = 'DA-RSBSA Official Enrollment Form';
    subEl.textContent = 'Applicant: ' + farmerName + ' | Ref #: ' + (sub.reference_no || 'N/A') + ' | Status: ' + st;
  } else {
    titleEl.textContent = 'Farm Registration Form';
    subEl.textContent = 'Farm: ' + (sub.farm_name || 'N/A') + ' | Owner: ' + farmerName + ' | Status: ' + st;
  }
  btnPdf.onclick = () => exportSubmissionPdf(type, sub);
  contentEl.innerHTML = buildSubmissionHtml(type, sub);
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  const vp = document.getElementById('daViewModalViewport');
  if (vp) { vp.scrollTop = 0; vp.scrollLeft = 0; }
}

/* ---------------- PDF (same layout as farmer's Download PDF) ---------------- */
async function exportSubmissionPdf(type, sub) {
  if (typeof html2pdf === 'undefined') {
    showToast('PDF library not loaded. Using print instead.', 'info');
    return printSubmission(type, sub);
  }
  const farmerName = sub.farmer_name || sub.applicant_name || 'Farmer';
  const filename = foFileName(type, farmerName, (sub.created_at || '').slice(0, 10).replace(/-/g, ''));

  const host = document.createElement('div');
  host.style.cssText = 'position:fixed;left:-10000px;top:0;width:8.5in;background:#fff;z-index:-1;';
  host.innerHTML = buildSubmissionHtml(type, sub);
  document.body.appendChild(host);
  const sheet = host.firstElementChild;
  sheet.classList.add('pdf-mode');

  showToast('Generating 8.5" x 13" PDF...', 'info');
  try {
    if (document.fonts && document.fonts.ready) await document.fonts.ready;
    await html2pdf().set({
      margin: 0,
      filename,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true, logging: false, scrollX: 0, scrollY: 0, windowWidth: 816, backgroundColor: '#ffffff' },
      jsPDF: { unit: 'in', format: [8.5, 13], orientation: 'portrait' },
      pagebreak: { mode: ['avoid-all'] }
    }).from(sheet).save();
    showToast('PDF downloaded!', 'success');
  } catch (e) {
    console.error(e);
    showToast('PDF failed, opening print dialog instead.', 'error');
    printSubmission(type, sub);
  } finally {
    host.remove();
  }
}

// Works from the table "PDF" button too (no modal needed)
function downloadSubmissionPdf(type, id) {
  fetchSubmission(type, id)
    .then(sub => exportSubmissionPdf(type, sub))
    .catch(err => alert('Failed to load submission: ' + err.message));
}

/* ---------------- Print ---------------- */
function printSubmission(type, sub) {
  let host = document.getElementById('daPrintHost');
  if (!host) { host = document.createElement('div'); host.id = 'daPrintHost'; document.body.appendChild(host); }
  host.innerHTML = buildSubmissionHtml(type, sub);
  document.body.classList.add('da-printing');
  const cleanup = () => { document.body.classList.remove('da-printing'); host.innerHTML = ''; window.removeEventListener('afterprint', cleanup); };
  window.addEventListener('afterprint', cleanup);
  setTimeout(() => window.print(), 150);
}

function printDaViewForm() {
  if (daCurrentView.sub) printSubmission(daCurrentView.type, daCurrentView.sub);
}

/* ============================================================== */
/* UNIFIED ASSISTANCE REQUESTS & OFFICIAL CERTIFICATES (VHC & COI) */
/* ============================================================== */

function switchDaAssistanceTab(tabKey) {
  const tabs = {
    unified: { btn: 'daSubtabBtnUnified', panel: 'daAssistanceTabUnified' },
    certs:   { btn: 'daSubtabBtnCerts',   panel: 'daAssistanceTabCerts' },
    legacy:  { btn: 'daSubtabBtnLegacy',  panel: 'daAssistanceTabLegacy' }
  };
  Object.keys(tabs).forEach(k => {
    const b = document.getElementById(tabs[k].btn);
    const p = document.getElementById(tabs[k].panel);
    if (b) b.classList.toggle('active', k === tabKey);
    if (p) p.style.display = (k === tabKey) ? 'block' : 'none';
  });
}

function filterDaAssistanceTable() {
  const typeFilter = (document.getElementById('daFilterAssistType')?.value || 'ALL').toLowerCase();
  const statusFilter = (document.getElementById('daFilterAssistStatus')?.value || 'ALL').toLowerCase();
  const search = (document.getElementById('daSearchAssistInput')?.value || '').toLowerCase().trim();

  const rows = document.querySelectorAll('#daTableUnifiedAssist tbody tr.da-assist-row');
  rows.forEach(r => {
    const rowType = (r.getAttribute('data-type') || '').toLowerCase();
    const rowStatus = (r.getAttribute('data-status') || '').toLowerCase();
    const rowSearch = (r.getAttribute('data-search') || '').toLowerCase();

    const matchType = (typeFilter === 'all' || rowType === typeFilter);
    const matchStatus = (statusFilter === 'all' || rowStatus === statusFilter);
    const matchSearch = (!search || rowSearch.includes(search));

    r.style.display = (matchType && matchStatus && matchSearch) ? '' : 'none';
  });
}

let daCurrentAssistReq = null;

async function openDaAssistanceReview(reqId) {
  const modal = document.getElementById('daAssistanceReviewModal');
  const bodyEl = document.getElementById('daArmBody');
  const titleEl = document.getElementById('daArmTitle');
  const subEl = document.getElementById('daArmSubtitle');
  if (!modal || !bodyEl) return;

  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  bodyEl.innerHTML = '<div style="text-align:center;padding:40px;color:#64748b;"><div class="spinner" style="margin:0 auto 10px;"></div>Loading assistance details...</div>';

  try {
    const fd = new FormData();
    fd.append('action', 'get_assistance_request_details');
    fd.append('request_id', reqId);

    const res = await fetch('agriDA.php', { method: 'POST', credentials: 'same-origin', body: fd });
    const data = await res.json();
    if (!data.success || !data.request) {
      throw new Error(data.message || 'Unable to load assistance request.');
    }

    const req = data.request;
    const certs = data.certificates || [];
    daCurrentAssistReq = req;

    titleEl.textContent = `${req.assistance_type} (${req.reference_no})`;
    subEl.textContent = `Submitted by ${req.farmer_name} on ${new Date(req.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'})}`;

    // Configure footer buttons
    const btnDel = document.getElementById('btnDaArmDelete');
    if (btnDel) btnDel.onclick = () => deleteDaAssistanceRequest(req.id);

    const btnSt = document.getElementById('btnDaArmStatus');
    if (btnSt) btnSt.onclick = () => openDaAssistanceStatusModal(req.id, req.status, req.official_remarks || '');

    const btnVhc = document.getElementById('btnDaArmVhc');
    if (btnVhc) btnVhc.onclick = () => openDaEncodeVhcModal(req.id);

    const btnCoi = document.getElementById('btnDaArmCoi');
    if (btnCoi) btnCoi.onclick = () => openDaEncodeCoiModal(req.id);

    // Status badge styling
    let stBg = '#f1f5f9', stClr = '#334155';
    if (['Request Submitted', 'Pending Review'].includes(req.status)) { stBg = '#fef3c7'; stClr = '#b45309'; }
    else if (['Under Review', 'For Inspection'].includes(req.status)) { stBg = '#dbeafe'; stClr = '#1e40af'; }
    else if (req.status === 'Approved') { stBg = '#d1fae5'; stClr = '#065f46'; }
    else if (req.status === 'Certificate Processing') { stBg = '#ede9fe'; stClr = '#6d28d9'; }
    else if (['Certificate Issued', 'Completed'].includes(req.status)) { stBg = '#dcfce7'; stClr = '#15803d'; }
    else if (req.status === 'Rejected') { stBg = '#fee2e2'; stClr = '#991b1b'; }

    // Parse treatment history
    let treatRows = [];
    if (req.treatment_history_json) {
      try {
        const parsed = JSON.parse(req.treatment_history_json);
        if (Array.isArray(parsed)) treatRows = parsed;
      } catch(e) {}
    }

    // Parse attachments
    let attachFiles = [];
    if (req.supporting_docs_json) {
      try {
        const parsed = JSON.parse(req.supporting_docs_json);
        if (Array.isArray(parsed)) attachFiles = parsed;
      } catch(e) {}
    }

    // Build Issued Certificates markup
    let certsHtml = '';
    if (certs.length > 0) {
      certsHtml = `
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:12px 16px;margin-bottom:1rem;">
          <h4 style="margin:0 0 8px;font-size:.9rem;color:#166534;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-award-fill"></i> Issued Official Certificates (${certs.length})
          </h4>
          <div style="display:flex;flex-direction:column;gap:8px;">
            ${certs.map(c => `
              <div style="display:flex;justify-content:space-between;align-items:center;background:#fff;padding:8px 12px;border-radius:8px;border:1px solid #cbd5e1;font-size:.82rem;">
                <div>
                  <strong style="color:#0f172a;font-family:monospace;">${escapeHtml(c.control_number)}</strong>
                  <span style="background:${c.cert_type === 'VHC' ? '#ecfdf5' : '#fdf2f8'};color:${c.cert_type === 'VHC' ? '#065f46' : '#9d174d'};padding:2px 6px;border-radius:4px;font-weight:700;margin-left:6px;font-size:.72rem;">${escapeHtml(c.cert_type)}</span>
                  <small style="color:#64748b;display:block;">Issued by: ${escapeHtml(c.vet_name)} (${escapeHtml(c.place_of_issue)}) on ${escapeHtml(c.issue_date)}</small>
                </div>
                <button type="button" class="an-btn" style="padding:.28rem .65rem;font-size:.75rem;background:#065f46;color:#fff;" onclick="viewDaAssistanceCertDoc(${c.id})">
                  <i class="bi bi-printer-fill"></i> View / Print
                </button>
              </div>
            `).join('')}
          </div>
        </div>
      `;
    }

    bodyEl.innerHTML = `
      <!-- Status & Header Banner -->
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:${stBg};border-radius:10px;margin-bottom:1.15rem;border:1px solid ${stClr}30;">
        <div style="display:flex;align-items:center;gap:10px;">
          <span style="font-size:1.25rem;color:${stClr};"><i class="bi bi-patch-check"></i></span>
          <div>
            <div style="font-size:.74rem;color:${stClr};text-transform:uppercase;font-weight:700;letter-spacing:0.5px;">Current Official Status</div>
            <strong style="font-size:.98rem;color:${stClr};">${escapeHtml(req.status)}</strong>
          </div>
        </div>
        <div style="text-align:right;">
          <small style="display:block;font-size:.72rem;color:#64748b;">Assigned Officer ID: ${req.assigned_officer_id ? '#' + req.assigned_officer_id : '<span style="color:#94a3b8;">Unassigned</span>'}</small>
          <small style="display:block;font-size:.72rem;color:#64748b;">Last Updated: ${req.updated_at ? new Date(req.updated_at).toLocaleString() : 'N/A'}</small>
        </div>
      </div>

      ${req.official_remarks ? `
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;margin-bottom:1.15rem;font-size:.82rem;">
          <strong style="color:#92400e;display:block;margin-bottom:2px;"><i class="bi bi-chat-left-dots-fill"></i> Official Remarks:</strong>
          <span style="color:#78350f;">${escapeHtml(req.official_remarks)}</span>
        </div>
      ` : ''}

      ${certsHtml}

      <!-- Grid: Section A & Section B -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin-bottom:1.15rem;">
        
        <!-- SECTION A: Farmer Information -->
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
          <h4 style="margin:0 0 8px;font-size:.84rem;font-weight:800;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:5px;">
            <i class="bi bi-person-badge-fill" style="color:#059669;"></i> SECTION A. Farmer Information
          </h4>
          <div style="font-size:.8rem;line-height:1.45;color:#334155;">
            <div><b>Full Name:</b> ${escapeHtml(req.farmer_name)}</div>
            <div><b>Farmer / Owner ID:</b> <code>FARMER-${String(req.farmer_id).padStart(5,'0')}</code></div>
            <div><b>Contact Number:</b> ${escapeHtml(req.farmer_contact || 'N/A')}</div>
            <div><b>Complete Address:</b> ${escapeHtml(req.farmer_address || 'N/A')}</div>
            <div><b>Barangay &amp; Muni:</b> ${escapeHtml((req.farmer_barangay ? req.farmer_barangay + ', ' : '') + (req.farmer_municipality || 'Camarines Sur'))}</div>
          </div>
        </div>

        <!-- SECTION B & C: Assistance Type & Livestock Information -->
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
          <h4 style="margin:0 0 8px;font-size:.84rem;font-weight:800;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:5px;">
            <i class="bi bi-card-checklist" style="color:#059669;"></i> SECTION B &amp; C. Livestock Details
          </h4>
          <div style="font-size:.8rem;line-height:1.45;color:#334155;">
            <div><b>Assistance Type:</b> <span style="font-weight:700;color:${req.assistance_type === 'Veterinary Assistance' ? '#059669' : '#be185d'};">${escapeHtml(req.assistance_type)}</span></div>
            <div><b>Kind of Animal / Species:</b> <strong>${escapeHtml(req.animal_species)}</strong></div>
            <div><b>Number of Heads:</b> <strong>${req.head_count}</strong></div>
            <div><b>Sex:</b> ${escapeHtml(req.sex)} ${req.mixed_breakdown ? '(' + escapeHtml(req.mixed_breakdown) + ')' : ''}</div>
            <div><b>Age:</b> ${escapeHtml(req.animal_age || 'N/A')} &bull; <b>Purpose:</b> ${escapeHtml(req.purpose || 'N/A')}</div>
            <div><b>Estimated Value:</b> ₱ ${parseFloat(req.estimated_value || 0).toLocaleString(undefined, {minimumFractionDigits:2})}</div>
            <div><b>Description / Physical Tags:</b> ${escapeHtml(req.animal_description || 'None provided')}</div>
          </div>
        </div>
      </div>

      <!-- Grid: Section D & Section E -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin-bottom:1.15rem;">
        
        <!-- SECTION D: Origin and Destination -->
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
          <h4 style="margin:0 0 8px;font-size:.84rem;font-weight:800;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:5px;">
            <i class="bi bi-geo-alt-fill" style="color:#059669;"></i> SECTION D. Origin &amp; Destination
          </h4>
          <div style="font-size:.8rem;line-height:1.45;color:#334155;">
            <div><b>Origin Barangay:</b> ${escapeHtml(req.origin_barangay || 'N/A')}</div>
            <div><b>Origin Municipality:</b> ${escapeHtml(req.origin_municipality || 'N/A')}</div>
            <div style="margin-top:6px;border-top:1px dashed #cbd5e1;padding-top:6px;">
              <div><b>Destination Municipality:</b> ${escapeHtml(req.dest_municipality || 'N/A')}</div>
              <div><b>Destination Barangay:</b> ${escapeHtml(req.dest_barangay || 'N/A')}</div>
              <div><b>Complete Destination Address:</b> ${escapeHtml(req.dest_address || 'N/A')}</div>
            </div>
          </div>
        </div>

        <!-- SECTION E: Transportation Details -->
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
          <h4 style="margin:0 0 8px;font-size:.84rem;font-weight:800;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:5px;">
            <i class="bi bi-truck" style="color:#059669;"></i> SECTION E. Transportation Details
          </h4>
          <div style="font-size:.8rem;line-height:1.45;color:#334155;">
            <div><b>Means of Transport:</b> ${escapeHtml(req.transport_means || 'N/A')}</div>
            <div><b>Vehicle Plate Number:</b> <code>${escapeHtml(req.plate_number || 'N/A')}</code></div>
            <div><b>Shipper / Driver Name:</b> ${escapeHtml(req.shipper_name || 'N/A')}</div>
            <div><b>Shipper Complete Address:</b> ${escapeHtml(req.shipper_address || 'N/A')}</div>
            <div><b>Shipper Contact No:</b> ${escapeHtml(req.shipper_contact || 'N/A')}</div>
            <div><b>Proposed Shipment Date:</b> ${escapeHtml(req.proposed_shipment_date || 'N/A')}</div>
            <div><b>Additional Notes:</b> ${escapeHtml(req.transport_details || 'None')}</div>
          </div>
        </div>
      </div>

      <!-- SECTION F: Treatment / Medication History -->
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;margin-bottom:1.15rem;">
        <h4 style="margin:0 0 8px;font-size:.84rem;font-weight:800;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:5px;">
          <i class="bi bi-capsule" style="color:#059669;"></i> SECTION F. Treatment / Medication History
        </h4>
        <div style="font-size:.8rem;margin-bottom:8px;">
          <b>Has received medication / treatment?</b> 
          <span style="font-weight:700;color:${req.has_treatment_history == 1 ? '#059669' : '#64748b'};">
            ${req.has_treatment_history == 1 ? 'YES (Declared by Farmer)' : 'NO (None declared)'}
          </span>
        </div>
        ${(req.has_treatment_history == 1 && treatRows.length > 0) ? `
          <table style="width:100%;border-collapse:collapse;font-size:.78rem;background:#fff;border-radius:6px;overflow:hidden;border:1px solid #cbd5e1;">
            <thead>
              <tr style="background:#f1f5f9;text-align:left;color:#334155;">
                <th style="padding:6px 10px;border-bottom:1px solid #cbd5e1;">Date of Treatment</th>
                <th style="padding:6px 10px;border-bottom:1px solid #cbd5e1;">Medicine Administered</th>
                <th style="padding:6px 10px;border-bottom:1px solid #cbd5e1;">Route of Administration</th>
                <th style="padding:6px 10px;border-bottom:1px solid #cbd5e1;">Withdrawal Period</th>
              </tr>
            </thead>
            <tbody>
              ${treatRows.map(tr => `
                <tr>
                  <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;">${escapeHtml(tr.date || 'N/A')}</td>
                  <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;font-weight:600;">${escapeHtml(tr.medicine || 'N/A')}</td>
                  <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;">${escapeHtml(tr.route || 'N/A')}</td>
                  <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;">${escapeHtml(tr.withdrawal || 'N/A')}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        ` : `
          <div style="color:#64748b;font-size:.78rem;font-style:italic;">No medication or treatment history declared by the farmer.</div>
        `}
        <div style="margin-top:6px;font-size:.72rem;color:#64748b;">
          <i class="bi bi-shield-exclamation"></i> <i>Note: Farmer-declared history is subject to clinical review and physical inspection by the attending veterinarian before issuing VHC.</i>
        </div>
      </div>

      <!-- SECTION G: Supporting Documents & Attachments -->
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;margin-bottom:1.15rem;">
        <h4 style="margin:0 0 8px;font-size:.84rem;font-weight:800;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:5px;">
          <i class="bi bi-paperclip" style="color:#059669;"></i> SECTION G. Supporting Documents &amp; Attachments
        </h4>
        ${attachFiles.length > 0 ? `
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            ${attachFiles.map(f => {
              const ext = (f.name || '').split('.').pop().toLowerCase();
              const isImg = ['jpg','jpeg','png','webp','gif'].includes(ext);
              const url = f.url || (f.path ? APP_BASE + '/' + f.path.replace(/^(\.\/|\/)/,'') : '#');
              if (isImg) {
                return `
                  <a href="${url}" target="_blank" style="display:block;border:1px solid #cbd5e1;border-radius:8px;overflow:hidden;width:100px;text-align:center;background:#fff;text-decoration:none;">
                    <img src="${url}" style="width:100px;height:75px;object-fit:cover;display:block;">
                    <span style="font-size:.7rem;padding:2px 4px;display:block;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(f.name || 'Photo')}</span>
                  </a>
                `;
              } else {
                return `
                  <a href="${url}" target="_blank" class="an-btn" style="background:#fff;border:1px solid #cbd5e1;color:#1e40af;font-size:.78rem;padding:.4rem .7rem;">
                    <i class="bi bi-file-earmark-pdf-fill" style="color:#dc2626;"></i> ${escapeHtml(f.name || 'Document')}
                  </a>
                `;
              }
            }).join('')}
          </div>
        ` : `
          <div style="color:#64748b;font-size:.78rem;font-style:italic;">No files attached by the farmer.</div>
        `}
      </div>

      <!-- SECTION H: Request Description & Farmer Certification -->
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
        <h4 style="margin:0 0 8px;font-size:.84rem;font-weight:800;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:5px;">
          <i class="bi bi-chat-text-fill" style="color:#059669;"></i> SECTION H. Request Description &amp; Certification
        </h4>
        <div style="font-size:.82rem;color:#334155;line-height:1.45;background:#fff;padding:10px;border-radius:8px;border:1px solid #cbd5e1;margin-bottom:8px;">
          ${escapeHtml(req.description || 'No specific description provided.')}
        </div>
        <div style="font-size:.74rem;color:#065f46;display:flex;align-items:center;gap:6px;">
          <i class="bi bi-check-circle-fill"></i>
          <span><b>Farmer Certification:</b> "${escapeHtml(req.certified_correct ? 'I certify that the information I have provided is true and accurate to the best of my knowledge.' : 'Certified accurate')}"</span>
        </div>
      </div>
    `;

  } catch(err) {
    console.error(err);
    bodyEl.innerHTML = `<div style="color:#ef4444;text-align:center;padding:30px;">Error loading assistance details: ${escapeHtml(err.message)}</div>`;
  }
}

function closeDaAssistanceReview() {
  const modal = document.getElementById('daAssistanceReviewModal');
  if (modal) modal.style.display = 'none';
  document.body.style.overflow = '';
}

function openDaAssistanceStatusModal(reqId, currentStatus, currentRemarks) {
  const modal = document.getElementById('daAssistanceStatusModal');
  if (!modal) return;
  document.getElementById('daStReqId').value = reqId;
  const sel = document.getElementById('daStSelectStatus');
  if (sel) sel.value = currentStatus || 'Pending Review';
  const rem = document.getElementById('daStRemarks');
  if (rem) rem.value = currentRemarks || '';
  modal.style.display = 'flex';
}

function closeDaAssistanceStatusModal() {
  const modal = document.getElementById('daAssistanceStatusModal');
  if (modal) modal.style.display = 'none';
}

async function submitDaAssistanceStatus() {
  const reqId = document.getElementById('daStReqId').value;
  const status = document.getElementById('daStSelectStatus').value;
  const remarks = document.getElementById('daStRemarks').value;
  const btn = document.getElementById('btnDaStSubmit');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;"></span> Saving...'; }

  try {
    const fd = new FormData();
    fd.append('action', 'update_assistance_status');
    fd.append('request_id', reqId);
    fd.append('status', status);
    fd.append('official_remarks', remarks);

    const res = await fetch('agriDA.php', { method: 'POST', credentials: 'same-origin', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to update status.');

    closeDaAssistanceStatusModal();
    closeDaAssistanceReview();
    if (typeof showToast === 'function') showToast(data.message, 'success');
    else alert(data.message);
    setTimeout(() => window.location.reload(), 700);
  } catch(err) {
    alert('Error updating status: ' + err.message);
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle"></i> Save Status'; }
  }
}

const DA_COI_RAGAY_LOGO = "data:image/png;base64,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";
const DA_COI_BAGONG_LOGO = "data:image/png;base64,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";

function daFmtLongDate(iso) {
  if (!iso) return '';
  const d = new Date(iso + (String(iso).length === 10 ? 'T00:00:00' : ''));
  if (isNaN(d.getTime())) return String(iso);
  return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}
function daFmtDayOrdinal(iso) {
  if (!iso) return { day: '', monthYear: '' };
  const d = new Date(iso + (String(iso).length === 10 ? 'T00:00:00' : ''));
  if (isNaN(d.getTime())) return { day: '', monthYear: String(iso) };
  const n = d.getDate();
  const suf = (n % 10 === 1 && n !== 11) ? 'st' : (n % 10 === 2 && n !== 12) ? 'nd' : (n % 10 === 3 && n !== 13) ? 'rd' : 'th';
  const my = d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  return { day: n + suf, monthYear: my };
}

function openDaEncodeVhcModal(reqId) {
  let req = (daCurrentAssistReq && daCurrentAssistReq.id == reqId)
    ? daCurrentAssistReq
    : (daAssistanceRequests.find(r => r.id == reqId) || {});

  const modal = document.getElementById('daEncodeVhcModal');
  if (!modal) return;

  const muniCode = (daOfficerMuni || 'RAG').slice(0, 3).toUpperCase();
  const today = new Date().toISOString().slice(0, 10);
  const expiry = new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10);

  document.getElementById('vhcReqId').value = reqId;
  document.getElementById('vhcControlNo').value = `VHC-${muniCode}-${new Date().getFullYear()}-${String(reqId).padStart(4, '0')}`;
  document.getElementById('vhcIssueDate').value = today;
  document.getElementById('vhcExpiryDate').value = expiry;

  document.getElementById('vhcOwnerName').value = req.farmer_name || '';
  const fAddr = [req.farmer_address, req.farmer_barangay, req.farmer_municipality || daOfficerMuni].filter(Boolean).join(', ');
  document.getElementById('vhcOwnerAddr').value = fAddr || ((daOfficerMuni || 'Ragay') + ', Camarines Sur');
  document.getElementById('vhcShipmentDate').value = req.proposed_shipment_date || today;

  const vet = daCurrentLguVet || { vet_name: 'Dana Mar F. San Andres, DVM', position: 'Ragay LGU Veterinarian', place: 'Ragay, Camarines Sur' };
  const defaultVetName = (daOfficerMuni === 'Ragay') ? 'Dana Mar F. San Andres, DVM' : (vet.name || vet.vet_name || 'Dana Mar F. San Andres, DVM');
  document.getElementById('vhcVetName').value = defaultVetName;
  document.getElementById('vhcVetPosition').value = (daOfficerMuni || 'Ragay') + ' LGU Veterinarian';
  document.getElementById('vhcPlaceOfIssue').value = vet.place || ((daOfficerMuni || 'Ragay') + ', Camarines Sur');
  document.getElementById('vhcPrcNo').value = document.getElementById('vhcPrcNo').value || '';
  document.getElementById('vhcPrcValidity').value = document.getElementById('vhcPrcValidity').value || '';
  document.getElementById('vhcVetContact').value = document.getElementById('vhcVetContact').value || '';

  document.getElementById('vhcSpecies').value = req.animal_species || 'Swine';
  document.getElementById('vhcHeads').value = req.head_count || 1;
  document.getElementById('vhcSex').value = req.sex || 'Mixed';
  document.getElementById('vhcAge').value = req.animal_age || 'Adult';
  document.getElementById('vhcPurpose').value = req.purpose || 'Slaughter';

  const orig = (req.origin_barangay ? req.origin_barangay + ', ' : '') + (req.origin_municipality || daOfficerMuni || 'Ragay') + ', Camarines Sur';
  const dest = req.dest_address || (req.dest_municipality ? (req.dest_barangay ? req.dest_barangay + ', ' : '') + req.dest_municipality : 'Camarines Sur');
  document.getElementById('vhcOrigin').value = orig;
  document.getElementById('vhcDest').value = dest;

  // Pre-fill treatment rows from farmer's declared treatment_history if present
  let trArr = [];
  try {
    if (req.treatment_history) {
      const parsed = typeof req.treatment_history === 'string' ? JSON.parse(req.treatment_history) : req.treatment_history;
      if (Array.isArray(parsed)) trArr = parsed;
    }
  } catch (e) {}
  ['1', '2'].forEach((n, idx) => {
    const row = trArr[idx] || {};
    document.getElementById('vhcTrDate' + n).value = row.date || '';
    document.getElementById('vhcTrMed' + n).value = row.medicine || '';
    document.getElementById('vhcTrRoute' + n).value = row.route || '';
    document.getElementById('vhcTrWith' + n).value = row.withdrawal || '';
  });

  modal.style.display = 'flex';
  modal.classList.add('active');
}

function closeDaEncodeVhcModal() {
  const modal = document.getElementById('daEncodeVhcModal');
  if (modal) { modal.classList.remove('active'); modal.style.display = 'none'; }
}

async function submitDaVhcCertificate(event) {
  if (event) event.preventDefault();
  const form = document.getElementById('daEncodeVhcForm');
  const btn = document.getElementById('btnSubmitVhc');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;"></span> Issuing...'; }

  try {
    const fd = new FormData(form);
    const res = await fetch(window.location.pathname, { method: 'POST', credentials: 'same-origin', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to encode VHC.');

    closeDaEncodeVhcModal();
    closeDaAssistanceReview();
    if (typeof showToast === 'function') showToast(data.message, 'success');
    if (data.cert) {
      const idx = daAssistanceCerts.findIndex(c => c.id == data.cert.id);
      if (idx >= 0) daAssistanceCerts[idx] = data.cert;
      else daAssistanceCerts.unshift(data.cert);
      viewDaAssistanceCertDoc(data.cert.id);
    } else {
      setTimeout(() => window.location.reload(), 700);
    }
  } catch (err) {
    alert('Error issuing VHC: ' + err.message);
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle"></i> Issue &amp; Register VHC Certificate'; }
  }
}

function openDaEncodeCoiModal(reqId) {
  let req = (daCurrentAssistReq && daCurrentAssistReq.id == reqId)
    ? daCurrentAssistReq
    : (daAssistanceRequests.find(r => r.id == reqId) || {});

  const modal = document.getElementById('daEncodeCoiModal');
  if (!modal) return;

  const muniCode = (daOfficerMuni || 'RAG').slice(0, 3).toUpperCase();
  const today = new Date().toISOString().slice(0, 10);
  const expiry = new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10);

  document.getElementById('coiReqId').value = reqId;
  document.getElementById('coiControlNo').value = `COI-${muniCode}-${new Date().getFullYear()}-${String(reqId).padStart(4, '0')}`;
  document.getElementById('coiInspectDate').value = today;
  document.getElementById('coiIssueDate').value = today;
  document.getElementById('coiExpiryDate').value = expiry;

  const vet = daCurrentLguVet || { vet_name: 'Dana Mar F. San Andres, DVM', position: 'Veterinarian I', place: 'Ragay, Camarines Sur' };
  const defaultVetName = (daOfficerMuni === 'Ragay') ? 'Dana Mar F. San Andres, DVM' : (vet.name || vet.vet_name || 'Dana Mar F. San Andres, DVM');
  document.getElementById('coiVetName').value = defaultVetName;
  document.getElementById('coiVetPosition').value = 'Veterinarian I';
  document.getElementById('coiMuniAgri').value = document.getElementById('coiMuniAgri').value || '';
  document.getElementById('coiPlaceOfIssue').value = 'Municipal Treasury Office, ' + (daOfficerMuni || 'Ragay') + ', Camarines Sur';

  document.getElementById('coiSpecies').value = req.animal_species || 'Swine';
  document.getElementById('coiHeads').value = req.head_count || 1;
  document.getElementById('coiValue').value = req.estimated_value || '15000.00';
  document.getElementById('coiPurpose').value = (req.purpose === 'Personal') ? 'Personal' : 'Commercial';

  const orig = (req.origin_municipality || daOfficerMuni || 'Ragay') + ', Camarines Sur';
  const dest = req.dest_address || (req.dest_municipality ? (req.dest_barangay ? req.dest_barangay + ', ' : '') + req.dest_municipality : 'Camarines Sur');
  document.getElementById('coiOrigin').value = orig;
  document.getElementById('coiDest').value = dest;

  document.getElementById('coiTransportMeans').value = req.transport_means || 'Forward Truck';
  document.getElementById('coiPlateNumber').value = req.plate_number || 'N/A';
  document.getElementById('coiShipper').value = req.shipper_name || req.farmer_name || 'Owner';
  document.getElementById('coiShipperAddr').value = req.shipper_address || req.farmer_address || orig;

  document.getElementById('coiOrNumber').value = 'OR-' + new Date().getFullYear() + '-' + Math.floor(100000 + Math.random() * 900000);
  document.getElementById('coiAmountPaid').value = '150.00';
  document.getElementById('coiPaymentDate').value = today;

  modal.style.display = 'flex';
  modal.classList.add('active');
}

function closeDaEncodeCoiModal() {
  const modal = document.getElementById('daEncodeCoiModal');
  if (modal) { modal.classList.remove('active'); modal.style.display = 'none'; }
}

async function submitDaCoiCertificate(event) {
  if (event) event.preventDefault();
  const form = document.getElementById('daEncodeCoiForm');
  const btn = document.getElementById('btnSubmitCoi');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;"></span> Issuing...'; }

  try {
    const fd = new FormData(form);
    const res = await fetch(window.location.pathname, { method: 'POST', credentials: 'same-origin', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to encode COI.');

    closeDaEncodeCoiModal();
    closeDaAssistanceReview();
    if (typeof showToast === 'function') showToast(data.message, 'success');
    if (data.cert) {
      const idx = daAssistanceCerts.findIndex(c => c.id == data.cert.id);
      if (idx >= 0) daAssistanceCerts[idx] = data.cert;
      else daAssistanceCerts.unshift(data.cert);
      viewDaAssistanceCertDoc(data.cert.id);
    } else {
      setTimeout(() => window.location.reload(), 700);
    }
  } catch (err) {
    alert('Error issuing COI: ' + err.message);
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle"></i> Issue &amp; Register COI Certificate'; }
  }
}

let daCurrentCert = null;
let daCurrentCertReq = null;

function viewDaAssistanceCertDoc(certId) {
  const cert = daAssistanceCerts.find(c => c.id == certId);
  if (!cert) {
    alert('Certificate not found.');
    return;
  }
  const req = daAssistanceRequests.find(r => r.id == cert.request_id) || {
    farmer_name: cert.farmer_name,
    farmer_id: cert.farmer_id,
    animal_species: cert.verified_species,
    head_count: cert.verified_heads,
    sex: cert.verified_sex,
    origin_municipality: cert.municipality
  };

  daCurrentCert = cert;
  daCurrentCertReq = req;

  const modal = document.getElementById('daAssistanceCertViewModal');
  const content = document.getElementById('daCertViewContent');
  const titleEl = document.getElementById('daCertViewTitle');
  const subEl = document.getElementById('daCertViewSubtitle');

  const isVhc = (cert.cert_type === 'VHC');
  titleEl.textContent = isVhc ? `Veterinary Health Certificate (${cert.control_number})` : `Certificate of Inspection (${cert.control_number})`;
  subEl.textContent = `Official ${cert.cert_type} issued to ${cert.owner_name || cert.farmer_name || req.farmer_name || ''} • ${cert.place_of_issue || ''}`;

  const sendBtn = document.getElementById('btnSendCertToFarmer');
  if (sendBtn) {
    sendBtn.style.display = 'inline-flex';
    sendBtn.disabled = false;
    sendBtn.innerHTML = '<i class="bi bi-send-check-fill"></i> Submit to Farmer Portal';
    sendBtn.style.background = '#059669';
    sendBtn.style.borderColor = '#059669';
  }
  content.innerHTML = buildDaOfficialCertificateHtml(cert, req);
  modal.style.display = 'flex';
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

async function sendDaCertToFarmerPortal() {
  if (!daCurrentCert || !daCurrentCert.id) {
    alert('No VHC/COI certificate selected.');
    return;
  }
  const btn = document.getElementById('btnSendCertToFarmer');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;"></span> Sending...';
  }
  try {
    const fd = new FormData();
    fd.append('action', 'send_cert_to_farmer_portal');
    fd.append('cert_id', daCurrentCert.id);
    const res = await fetch(window.location.pathname, { method: 'POST', credentials: 'same-origin', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to send certificate to Farmer Portal.');
    if (typeof showToast === 'function') showToast(data.message, 'success');
    else alert(data.message);
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Submitted to Farmer Portal';
      btn.style.background = '#047857';
      btn.style.borderColor = '#047857';
    }
  } catch (err) {
    alert('Error sending certificate: ' + err.message);
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send-check-fill"></i> Submit to Farmer Portal';
    }
  }
}

function closeDaAssistanceCertView() {
  const modal = document.getElementById('daAssistanceCertViewModal');
  if (modal) { modal.classList.remove('active'); modal.style.display = 'none'; }
  document.body.style.overflow = '';
}

function buildDaOfficialCertificateHtml(cert, req) {
  const isVhc = (cert.cert_type === 'VHC');
  const muni = cert.municipality || req.origin_municipality || daOfficerMuni || 'Ragay';

  if (!isVhc) {
    // EXACT OFFICIAL CERTIFICATE OF INSPECTION (COI) TEMPLATE
    const inspDate = daFmtLongDate(cert.inspection_date || cert.issue_date);
    const issuedParts = daFmtDayOrdinal(cert.issue_date);
    const validUntil = daFmtLongDate(cert.expiry_date || cert.issue_date);
    const payDate = daFmtLongDate(cert.payment_date || cert.issue_date);
    const isPersonal = String(cert.purpose || '').toLowerCase() === 'personal';
    const isCommercial = !isPersonal;
    const valNum = parseFloat(cert.value || req.estimated_value || 0);
    const amtNum = parseFloat(cert.amount_paid || 0);

    return `
      <div id="daPrintableCertSheet" class="printable-cert-sheet" style="background:#fff;width:7in;max-width:100%;margin:0 auto;padding:0.55in 0.6in;box-sizing:content-box;font-family:'Calibri','Arial',sans-serif;font-size:11pt;line-height:1.4;color:#000;box-shadow:0 4px 16px rgba(0,0,0,.08);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;text-align:center;">
          ${DA_COI_RAGAY_LOGO ? `<img src="${DA_COI_RAGAY_LOGO}" alt="Municipality Seal" style="width:80px;height:80px;object-fit:contain;">` : '<div style="width:80px;"></div>'}
          <div style="flex:1;padding:0 15px;">
            <p style="margin:0;font-family:'Old English Text MT','Times New Roman',serif;font-size:11pt;">Republic of the Philippines</p>
            <p style="margin:0;font-family:'Times New Roman',Times,serif;font-size:11pt;">Province of Camarines Sur</p>
            <p style="margin:0;font-family:'Times New Roman',Times,serif;font-size:11pt;">Municipality of ${escapeHtml(muni)}</p>
            <h2 style="margin:4px 0 0 0;font-family:'Calibri','Arial',sans-serif;font-size:13pt;font-weight:bold;letter-spacing:0.5px;">OFFICE OF THE MUNICIPAL AGRICULTURIST</h2>
          </div>
          ${DA_COI_BAGONG_LOGO ? `<img src="${DA_COI_BAGONG_LOGO}" alt="Bagong Pilipinas" style="width:80px;height:80px;object-fit:contain;">` : '<div style="width:80px;"></div>'}
        </div>

        <div style="text-align:center;font-weight:bold;font-size:14pt;margin:22px 0 18px 0;letter-spacing:1px;">CERTIFICATE OF INSPECTION</div>

        <p style="margin:0 0 14px 0;">
          I hereby certify that the following animal(s) with the corresponding details had been inspected on
          <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;text-align:center;">${escapeHtml(inspDate)}</span>
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
              <td style="padding-top:5px;"><span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;">${escapeHtml(cert.verified_species || req.animal_species || '')}</span></td>
              <td style="padding-top:5px;"><span style="display:inline-block;border-bottom:1px solid #000;min-width:80px;padding:0 6px;font-weight:bold;">${escapeHtml(String(cert.verified_heads || req.head_count || ''))}</span></td>
              <td style="padding-top:5px;"><span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;">Php ${valNum.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</span></td>
            </tr>
          </tbody>
        </table>

        <div style="display:flex;justify-content:space-between;margin-bottom:22px;">
          <div style="width:52%;">
            <div style="margin-bottom:8px;">Means of transport: <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 4px;font-weight:bold;">${escapeHtml(cert.transport_means || '')}</span></div>
            <div style="margin-bottom:8px;">Destination: <span style="display:inline-block;border-bottom:1px solid #000;min-width:220px;padding:0 4px;font-weight:bold;">${escapeHtml(cert.destination || '')}</span></div>
            <div style="margin-bottom:8px;">Plate No.: <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 4px;font-weight:bold;">${escapeHtml(cert.plate_number || '')}</span></div>
            <div style="margin-top:15px;margin-bottom:8px;">Shipper: <span style="display:inline-block;border-bottom:1px solid #000;min-width:220px;padding:0 4px;font-weight:bold;">${escapeHtml(cert.shipper || req.farmer_name || '')}</span></div>
          </div>
          <div style="width:46%;">
            <div style="margin-bottom:8px;">Origin: <strong>${escapeHtml(cert.origin || (muni + ', Camarines Sur'))}</strong></div>
            <div style="margin-bottom:8px;">Purpose: ( <strong>${isCommercial ? '✓' : '&nbsp;'}</strong> ) Commercial &nbsp;&nbsp; ( <strong>${isPersonal ? '✓' : '&nbsp;'}</strong> ) Personal</div>
            <div style="margin-top:38px;margin-bottom:8px;">Address: <span style="display:inline-block;border-bottom:1px solid #000;min-width:210px;padding:0 4px;font-weight:bold;">${escapeHtml(cert.shipper_address || req.farmer_address || '')}</span></div>
          </div>
        </div>

        <p style="margin-top:25px;">
          Issued this <span style="display:inline-block;border-bottom:1px solid #000;min-width:45px;padding:0 4px;font-weight:bold;text-align:center;">${escapeHtml(issuedParts.day)}</span>
          day of <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;text-align:center;">${escapeHtml(issuedParts.monthYear)}</span>
          at the Municipal Agriculture Office, ${escapeHtml(muni)}, Camarines Sur.
        </p>

        <div style="margin-top:30px;margin-bottom:22px;">
          <div style="text-align:right;margin-bottom:25px;">
            <div style="display:inline-block;text-align:center;">
              <div style="height:26px;"></div>
              <span style="font-weight:bold;border-bottom:1px solid #000;padding:0 10px 2px 10px;min-width:220px;display:inline-block;">${escapeHtml(cert.vet_name || '')}</span><br>
              <span>${escapeHtml(cert.vet_position || 'Veterinarian I')}</span>
            </div>
          </div>
          <div style="text-align:left;">
            <div>NOTED:</div>
            <div style="display:inline-block;text-align:center;margin-top:20px;">
              <div style="height:22px;"></div>
              <span style="font-weight:bold;border-bottom:1px solid #000;padding:0 10px 2px 10px;min-width:220px;display:inline-block;">${escapeHtml(cert.municipal_agriculturist || '')}</span><br>
              <span>Municipal Agriculturist</span>
            </div>
          </div>
        </div>

        <div style="margin-bottom:20px;">
          <div style="margin-bottom:6px;">Paid under O. R. No.: <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;">${escapeHtml(cert.or_number || '')}</span></div>
          <div style="margin-bottom:6px;">Amount paid: Php <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;">${amtNum.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</span></div>
          <div style="margin-bottom:6px;">Date: <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;">${escapeHtml(payDate)}</span></div>
          <div style="margin-bottom:6px;">Place of Issue: <strong>${escapeHtml(cert.place_of_issue || ('Municipal Treasury Office, ' + muni + ', Camarines Sur'))}</strong></div>
        </div>

        <div style="margin-bottom:24px;">
          <div>PLEASE NOTE:</div>
          <ol style="margin:5px 0 0 20px;padding:0;">
            <li style="margin-bottom:4px;">This certification is valid until <span style="display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 6px;font-weight:bold;text-align:center;">${escapeHtml(validUntil)}</span> and subject to cancellation should a disease outbreak occur in the place of origin.</li>
            <li style="margin-bottom:4px;">The above-mentioned animal(s) is/are still subject for further inspection for any purpose(s) deemed necessary.</li>
            <li style="margin-bottom:4px;">This is a requirement for the issuance of Veterinary Health Certificate.</li>
          </ol>
        </div>

        <div style="text-align:center;margin-top:24px;page-break-inside:avoid;">
          <div style="font-style:italic;font-weight:bold;font-size:13pt;color:#444;margin-bottom:5px;line-height:1.2;">Better Greater Happier<br>${escapeHtml(muni)}</div>
          <div style="font-size:9pt;color:#333;">
            Office of the Municipal Agriculturist, 2F Municipal Bldg., Poblacion, ${escapeHtml(muni)}, Camarines Sur<br>
            Tel No.: 331-22-46 Email Add.: maoragay01@gmail.com
          </div>
        </div>
      </div>
    `;
  }

  // EXACT OFFICIAL VETERINARY HEALTH CERTIFICATE (VHC) TEMPLATE
  const certDate = daFmtLongDate(cert.issue_date);
  const expDate = daFmtLongDate(cert.expiry_date);
  const shipDate = daFmtLongDate(cert.shipment_date || req.proposed_shipment_date || cert.issue_date);
  const ownerName = cert.owner_name || req.farmer_name || cert.farmer_name || '';
  const ownerAddr = cert.owner_address || [req.farmer_address, req.farmer_barangay, req.farmer_municipality || muni].filter(Boolean).join(', ');
  let trRows = [];
  try {
    if (cert.treatment_rows) {
      const p = typeof cert.treatment_rows === 'string' ? JSON.parse(cert.treatment_rows) : cert.treatment_rows;
      if (Array.isArray(p)) trRows = p;
    }
  } catch (e) {}
  while (trRows.length < 2) trRows.push({ date: 'N/A', medicine: 'N/A', route: 'N/A', withdrawal: 'N/A' });

  return `
    <div id="daPrintableCertSheet" class="printable-cert-sheet" style="background:#fff;width:7in;max-width:100%;margin:0 auto;padding:0.55in 0.6in;box-sizing:content-box;font-family:'Cambria','Times New Roman',Times,serif;font-size:11pt;line-height:1.45;color:#000;box-shadow:0 4px 16px rgba(0,0,0,.08);">
      <div style="text-align:center;font-weight:bold;font-size:17pt;letter-spacing:0.5px;margin:0 0 24px 0;">VETERINARY HEALTH CERTIFICATE</div>

      <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:26px;">
        <div style="text-align:center;">
          <span style="display:block;border-bottom:1px solid #000;min-width:180px;min-height:1.4em;padding:0 6px;font-weight:bold;">${escapeHtml(certDate)}</span>
          <span style="font-size:10pt;">Date</span>
        </div>
        <div style="text-align:center;">
          <span style="display:block;border-bottom:1px solid #000;min-width:190px;min-height:1.4em;padding:0 6px;font-weight:bold;">${escapeHtml(cert.control_number || '')}</span>
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
            <td style="border:1px solid #000;padding:6px 8px;text-align:center;font-weight:bold;">${escapeHtml(cert.verified_species || req.animal_species || '')}</td>
            <td style="border:1px solid #000;padding:6px 8px;text-align:center;font-weight:bold;">${escapeHtml(String(cert.verified_heads || req.head_count || ''))}</td>
            <td style="border:1px solid #000;padding:6px 8px;text-align:center;font-weight:bold;">${escapeHtml(cert.verified_sex || req.sex || '')}</td>
            <td style="border:1px solid #000;padding:6px 8px;text-align:center;font-weight:bold;">${escapeHtml(cert.verified_age || req.animal_age || '')}</td>
            <td style="border:1px solid #000;padding:6px 8px;text-align:center;font-weight:bold;">${escapeHtml(cert.purpose || req.purpose || '')}</td>
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
        <div style="margin-bottom:6px;">Owner: <span style="display:inline-block;border-bottom:1px solid #000;min-width:340px;padding:0 6px;font-weight:bold;">${escapeHtml(ownerName)}</span></div>
        <div style="margin-bottom:6px;">Address: <span style="display:inline-block;border-bottom:1px solid #000;min-width:330px;padding:0 6px;font-weight:bold;">${escapeHtml(ownerAddr)}</span></div>
        <div style="margin-bottom:6px;">Origin: <span style="display:inline-block;border-bottom:1px solid #000;min-width:340px;padding:0 6px;font-weight:bold;">${escapeHtml(cert.origin || '')}</span></div>
        <div style="margin-bottom:6px;">Destination: <span style="display:inline-block;border-bottom:1px solid #000;min-width:315px;padding:0 6px;font-weight:bold;">${escapeHtml(cert.destination || '')}</span></div>
        <div style="margin-bottom:6px;">Date of Shipment: <span style="display:inline-block;border-bottom:1px solid #000;min-width:275px;padding:0 6px;font-weight:bold;">${escapeHtml(shipDate)}</span></div>
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
            <td style="border:1px solid #000;padding:5px 8px;">${escapeHtml(r.date || 'N/A')}</td>
            <td style="border:1px solid #000;padding:5px 8px;">${escapeHtml(r.medicine || 'N/A')}</td>
            <td style="border:1px solid #000;padding:5px 8px;">${escapeHtml(r.route || 'N/A')}</td>
            <td style="border:1px solid #000;padding:5px 8px;">${escapeHtml(r.withdrawal || 'N/A')}</td>
          </tr>`).join('')}
        </tbody>
      </table>

      <p style="margin:22px 0 30px 0;">
        This certificate shall expire on <span style="display:inline-block;border-bottom:1px solid #000;min-width:190px;padding:0 6px;font-weight:bold;text-align:center;">${escapeHtml(expDate)}</span>, and automatically cancelled thereafter.
      </p>

      <div style="margin-left:0;">
        <div style="height:40px;"></div>
        <div style="font-weight:bold;text-decoration:underline;text-transform:uppercase;">${escapeHtml(cert.vet_name || 'Dana Mar F. San Andres, DVM')}</div>
        <div style="margin-top:2px;">${escapeHtml(cert.vet_position || (muni + ' LGU Veterinarian'))}</div>
        <div style="margin-top:2px;">PRC License No.: <span style="display:inline-block;border-bottom:1px solid #000;min-width:200px;padding:0 6px;">${escapeHtml(cert.prc_license_no || '')}</span></div>
        <div style="margin-top:2px;">Validity: <span style="display:inline-block;border-bottom:1px solid #000;min-width:200px;padding:0 6px;">${escapeHtml(cert.prc_validity || '')}</span></div>
        <div style="margin-top:2px;">Contact No.: <span style="display:inline-block;border-bottom:1px solid #000;min-width:200px;padding:0 6px;">${escapeHtml(cert.vet_contact_no || '')}</span></div>
      </div>

      <div style="margin-top:36px;font-size:10.5pt;text-align:justify;">
        Note: This is not a valid &ldquo;Shipping Permit&rdquo;, please secure a Shipping Permit from the National Veterinary
        Quarantine Service-Veterinary Quarantine Station 5, (nvqsd.bai.gov.ph). ANY UNAUTHORIZED
        ALTERATION INVALIDATES THIS CERTIFICATE.
      </div>
    </div>
  `;
}

// ── INDEMNITY CLAIMS REQUIREMENTS & CERTIFICATE CONTROLLER ────────────────────
function openDaIndemnityModal(insObj) {
  const modal = document.getElementById('daIndemnityCertModal');
  const form = document.getElementById('daIndemnityCertForm');
  if (!modal || !form) return;
  form.reset();

  const muniCode = (daOfficerMuni || 'RAG').slice(0, 3).toUpperCase();
  const today = new Date().toISOString().slice(0, 10);
  document.getElementById('indControlNo').value = `IND-${muniCode}-${new Date().getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`;
  document.getElementById('indIssueDate').value = today;
  document.getElementById('indDateLoss').value = today;

  const vet = daCurrentLguVet || { vet_name: 'Dana Mar F. San Andres, DVM' };
  document.getElementById('indVetName').value = (daOfficerMuni === 'Ragay') ? 'Dana Mar F. San Andres, DVM' : (vet.name || vet.vet_name || 'Dana Mar F. San Andres, DVM');

  if (insObj) {
    document.getElementById('indInsId').value = insObj.id || '';
    document.getElementById('indFarmerId').value = insObj.farmer_id || '';
    document.getElementById('indFarmerName').value = insObj.farmer_name || '';
    document.getElementById('indFarmerBrgy').value = insObj.farmer_barangay || '';
    document.getElementById('indAnimalKind').value = insObj.livestock_type || '';
    document.getElementById('indEstLoss').value = insObj.estimated_loss || '0.00';
  }
  modal.style.display = 'flex';
  modal.classList.add('active');
}

function closeDaIndemnityModal() {
  const modal = document.getElementById('daIndemnityCertModal');
  if (modal) { modal.classList.remove('active'); modal.style.display = 'none'; }
}

async function submitDaIndemnityCert(event) {
  if (event) event.preventDefault();
  const form = document.getElementById('daIndemnityCertForm');
  const btn = document.getElementById('btnSubmitIndemnity');
  if (btn) { btn.disabled = true; btn.innerHTML = 'Issuing...'; }
  try {
    const fd = new FormData(form);
    const res = await fetch(window.location.pathname, { method: 'POST', credentials: 'same-origin', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to issue indemnity certificate.');
    closeDaIndemnityModal();
    if (typeof showToast === 'function') showToast(data.message, 'success');
    if (data.cert) {
      daIndemnityCerts.unshift(data.cert);
      viewDaIndemnityCertDoc(data.cert.id);
    } else {
      setTimeout(() => window.location.reload(), 700);
    }
  } catch (err) {
    alert(err.message);
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle"></i> Issue Indemnity Claim Certificate'; }
  }
}

function viewDaIndemnityCertDoc(id) {
  const ic = daIndemnityCerts.find(x => x.id == id);
  if (!ic) { alert('Indemnity certificate not found.'); return; }
  daCurrentCert = { control_number: ic.control_number };
  const sendBtnInd = document.getElementById('btnSendCertToFarmer');
  if (sendBtnInd) sendBtnInd.style.display = 'none';

  const modal = document.getElementById('daAssistanceCertViewModal');
  const content = document.getElementById('daCertViewContent');
  const titleEl = document.getElementById('daCertViewTitle');
  const subEl = document.getElementById('daCertViewSubtitle');

  titleEl.textContent = `Indemnity Claim Verification Certificate (${ic.control_number})`;
  subEl.textContent = `Issued to ${ic.farmer_name} • Brgy. ${ic.farmer_barangay || ''}, ${ic.municipality || 'Ragay'}`;

  const issuedParts = daFmtDayOrdinal(ic.issue_date);
  const muni = ic.municipality || daOfficerMuni || 'Ragay';
  content.innerHTML = `
    <div id="daPrintableCertSheet" class="printable-cert-sheet" style="background:#fff;width:7in;max-width:100%;margin:0 auto;padding:0.55in 0.6in;box-sizing:content-box;font-family:'Calibri','Arial',sans-serif;font-size:11pt;line-height:1.45;color:#000;box-shadow:0 4px 16px rgba(0,0,0,.08);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;text-align:center;">
        ${DA_COI_RAGAY_LOGO ? `<img src="${DA_COI_RAGAY_LOGO}" alt="Municipality Seal" style="width:76px;height:76px;object-fit:contain;">` : '<div style="width:76px;"></div>'}
        <div style="flex:1;padding:0 12px;">
          <p style="margin:0;font-family:'Old English Text MT','Times New Roman',serif;font-size:11pt;">Republic of the Philippines</p>
          <p style="margin:0;font-family:'Times New Roman',Times,serif;font-size:11pt;">Province of Camarines Sur &bull; Municipality of ${escapeHtml(muni)}</p>
          <h2 style="margin:4px 0 0 0;font-size:12.5pt;font-weight:bold;">OFFICE OF THE MUNICIPAL AGRICULTURIST</h2>
        </div>
        ${DA_COI_BAGONG_LOGO ? `<img src="${DA_COI_BAGONG_LOGO}" alt="Bagong Pilipinas" style="width:76px;height:76px;object-fit:contain;">` : '<div style="width:76px;"></div>'}
      </div>

      <div style="text-align:center;font-weight:bold;font-size:13.5pt;margin:14px 0 6px 0;letter-spacing:.6px;text-decoration:underline;">
        CERTIFICATE OF INDEMNITY CLAIM VERIFICATION &amp; ENDORSEMENT
      </div>
      <div style="text-align:center;font-size:9.5pt;margin-bottom:18px;">
        Control No.: <strong>${escapeHtml(ic.control_number)}</strong> &nbsp;|&nbsp; PCIC Livestock Insurance Indemnity Claim
      </div>

      <p style="text-align:justify;margin-bottom:14px;">
        This is to certify that <strong>${escapeHtml(ic.farmer_name)}</strong> of Barangay <strong>${escapeHtml(ic.farmer_barangay || 'N/A')}</strong>,
        ${escapeHtml(muni)}, Camarines Sur, has submitted and complied with all documentary requirements for Livestock Insurance
        Indemnity Claim relative to the reported mortality loss detailed below:
      </p>

      <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:10.5pt;">
        <tr><td style="border:1px solid #000;padding:5px 8px;width:36%;font-weight:bold;">Name of Farmer / Owner</td><td style="border:1px solid #000;padding:5px 8px;">${escapeHtml(ic.farmer_name)}</td></tr>
        <tr><td style="border:1px solid #000;padding:5px 8px;font-weight:bold;">Barangay / Municipality</td><td style="border:1px solid #000;padding:5px 8px;">Brgy. ${escapeHtml(ic.farmer_barangay || 'N/A')}, ${escapeHtml(muni)}, Camarines Sur</td></tr>
        <tr><td style="border:1px solid #000;padding:5px 8px;font-weight:bold;">Kind of Animal &amp; Gender</td><td style="border:1px solid #000;padding:5px 8px;">${escapeHtml(ic.animal_kind_gender)}</td></tr>
        <tr><td style="border:1px solid #000;padding:5px 8px;font-weight:bold;">Date of Loss</td><td style="border:1px solid #000;padding:5px 8px;">${escapeHtml(daFmtLongDate(ic.date_of_loss))}</td></tr>
        <tr><td style="border:1px solid #000;padding:5px 8px;font-weight:bold;">Cause of Death</td><td style="border:1px solid #000;padding:5px 8px;">${escapeHtml(ic.cause_of_death)}</td></tr>
        <tr><td style="border:1px solid #000;padding:5px 8px;font-weight:bold;">Estimated Loss Value</td><td style="border:1px solid #000;padding:5px 8px;">Php ${parseFloat(ic.estimated_loss || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td></tr>
      </table>

      <div style="font-weight:bold;margin-bottom:6px;">VERIFIED INDEMNITY CLAIM REQUIREMENTS:</div>
      <ul style="margin:0 0 16px 20px;padding:0;font-size:10pt;">
        <li>[&#10003;] <strong>Barangay Certification</strong> (Name of Farmer, Kind of Animal &amp; Gender, Date of Loss, Cause of Death)</li>
        <li>[&#10003;] <strong>Witness 1 (Same Barangay, Not Related):</strong> ${escapeHtml(ic.witness1_name || '')} &mdash; Cedula No.: ${escapeHtml(ic.witness1_cedula || 'Verified')} (Affidavit &amp; Valid ID verified)</li>
        <li>[&#10003;] <strong>Witness 2 (Same Barangay, Not Related):</strong> ${escapeHtml(ic.witness2_name || '')} &mdash; Cedula No.: ${escapeHtml(ic.witness2_cedula || 'Verified')} (Affidavit &amp; Valid ID verified)</li>
        <li>[&#10003;] <strong>Owner's Affidavit</strong> (sworn narration of the incident)</li>
        <li>[&#10003;] <strong>Mortality Evidence Photos</strong> (${escapeHtml(ic.photo_receiver_note || 'Submitted & Verified')})</li>
      </ul>

      <p style="margin:18px 0 26px 0;">
        Issued this <strong>${escapeHtml(issuedParts.day)}</strong> day of <strong>${escapeHtml(issuedParts.monthYear)}</strong>
        at the Office of the Municipal Agriculturist, ${escapeHtml(muni)}, Camarines Sur, for PCIC Livestock Insurance Indemnity Claim processing.
      </p>

      <div style="display:flex;justify-content:space-between;margin-top:34px;">
        <div style="text-align:center;width:45%;">
          <div style="font-size:9.5pt;margin-bottom:26px;">VERIFIED BY:</div>
          <div style="font-weight:bold;border-bottom:1px solid #000;padding-bottom:2px;">${escapeHtml(ic.vet_name || 'Dana Mar F. San Andres, DVM')}</div>
          <div style="font-size:9.5pt;">Municipal Veterinarian / Livestock Officer</div>
        </div>
        <div style="text-align:center;width:45%;">
          <div style="font-size:9.5pt;margin-bottom:26px;">NOTED BY:</div>
          <div style="font-weight:bold;border-bottom:1px solid #000;padding-bottom:2px;">${escapeHtml(ic.municipal_agriculturist || '')}</div>
          <div style="font-size:9.5pt;">Municipal Agriculturist</div>
        </div>
      </div>
    </div>
  `;
  modal.style.display = 'flex';
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function printDaOfficialCertDoc() {
  const content = document.getElementById('daCertViewContent');
  if (!content) return;
  let host = document.getElementById('daPrintHost');
  if (!host) {
    host = document.createElement('div');
    host.id = 'daPrintHost';
    document.body.appendChild(host);
  }
  host.innerHTML = content.innerHTML;
  document.body.classList.add('da-printing');
  const cleanup = () => {
    document.body.classList.remove('da-printing');
    host.innerHTML = '';
    window.removeEventListener('afterprint', cleanup);
  };
  window.addEventListener('afterprint', cleanup);
  setTimeout(() => window.print(), 150);
}

async function downloadDaOfficialCertPdf() {
  const sheet = document.getElementById('daPrintableCertSheet');
  if (!sheet) return;
  const ctrl = daCurrentCert ? daCurrentCert.control_number : 'CERTIFICATE';
  const filename = `${ctrl}_Official_Certificate.pdf`;

  if (typeof showToast === 'function') showToast('Generating PDF certificate...', 'info');

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
    if (typeof showToast === 'function') showToast('PDF certificate downloaded!', 'success');
  } catch(e) {
    console.error(e);
    alert('PDF generation unavailable, opening printer dialog instead.');
    printDaOfficialCertDoc();
  }
}

async function deleteDaAssistanceRequest(reqId) {
  if (!confirm('Are you sure you want to permanently delete this assistance request and any associated certificates?')) return;
  try {
    const fd = new FormData();
    fd.append('action', 'delete_assistance_request');
    fd.append('id', reqId);
    const res = await fetch('agriDA.php', { method: 'POST', credentials: 'same-origin', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to delete request.');
    if (typeof showToast === 'function') showToast(data.message, 'success');
    else alert(data.message);
    setTimeout(() => window.location.reload(), 600);
  } catch(err) {
    alert('Error deleting request: ' + err.message);
  }
}

</script>
<script src="<?= app_url('assets/js/translator.js') ?>"></script>
</body>
</html>


