<?php
// helpers.php — shared helpers for URL building and profile avatars.
// Pages are served through .htaccess rewrites (e.g. /AG/farmer.php -> /AG/pages/farmer.php),
// so relative paths like "../api/" or "../maps/" resolve to the wrong place in the browser.
// Always build asset/api/map URLs from app_base() instead.

if (!function_exists('app_base')) {
    /** Web path of the project root, e.g. "/AG" (or "" if served from the domain root). */
    function app_base(): string {
        static $base = null;
        if ($base !== null) return $base;

        $root = str_replace('\\', '/', (string)realpath(__DIR__));
        $doc  = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));

        if ($doc !== '' && stripos($root, $doc) === 0) {
            $base = rtrim(substr($root, strlen($doc)), '/');
        } else {
            // Fallback: derive from the requested URL
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $base = rtrim(preg_replace('#/(?:pages/|maps/|api/)?[^/]*$#', '', $path), '/');
        }
        return $base;
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string {
        return app_base() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('user_pix_url')) {
    /** Returns a web URL for a stored profile picture, or null if missing on disk. */
    function user_pix_url(?string $relPath): ?string {
        if (!$relPath) return null;
        $rel = ltrim(str_replace('\\', '/', $relPath), '/');
        if (!is_file(__DIR__ . '/' . $rel)) return null;
        $v = @filemtime(__DIR__ . '/' . $rel) ?: time();
        return app_url($rel) . '?v=' . $v;
    }
}

if (!function_exists('avatar_inner')) {
    /** Inner HTML for an avatar circle: the photo if there is one, otherwise the initial. */
    function avatar_inner(?string $pixUrl, string $initial): string {
        if ($pixUrl) {
            return '<img class="av-img" src="' . htmlspecialchars($pixUrl, ENT_QUOTES, 'UTF-8') . '" alt="">';
        }
        return htmlspecialchars($initial, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ensure_agritrace_extended_tables')) {
    /** Safely auto-create extended tables and columns if not present */
    function ensure_agritrace_extended_tables(PDO $pdo): void {
        ensure_assistance_tables($pdo);
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `livestock_health_records` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `livestock_id` int(11) NOT NULL,
              `farm_id` int(11) DEFAULT NULL,
              `record_type` enum('Health Check','Vaccination','Deworming','Veterinary Treatment','Other') NOT NULL DEFAULT 'Health Check',
              `health_condition` enum('Healthy','Sick','Under Treatment','Critical','Recovered','Deceased') NOT NULL DEFAULT 'Healthy',
              `treatment_details` text DEFAULT NULL,
              `medicine_administered` varchar(255) DEFAULT NULL,
              `dosage` varchar(100) DEFAULT NULL,
              `administered_by` varchar(255) DEFAULT NULL,
              `checkup_date` date NOT NULL,
              `next_checkup_date` date DEFAULT NULL,
              `notes` text DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_lhr_livestock` (`livestock_id`),
              KEY `idx_lhr_farm` (`farm_id`),
              KEY `idx_lhr_date` (`checkup_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `veterinary_requests` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `request_code` varchar(50) NOT NULL UNIQUE,
              `farmer_id` int(11) NOT NULL,
              `farm_id` int(11) NOT NULL,
              `request_type` enum('Veterinary consultation','Emergency animal treatment','Vaccination','Deworming','Farm inspection','Breeding assistance') NOT NULL,
              `urgency` enum('Normal','Urgent','Emergency') NOT NULL DEFAULT 'Normal',
              `livestock_type` varchar(100) DEFAULT NULL,
              `animal_count` int(11) NOT NULL DEFAULT 1,
              `preferred_date` date DEFAULT NULL,
              `description` text NOT NULL,
              `status` enum('Pending','Assigned','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
              `assigned_officer_id` int(11) DEFAULT NULL,
              `officer_remarks` text DEFAULT NULL,
              `attachment_url` varchar(500) DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NULL DEFAULT current_timestamp ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_vr_farmer` (`farmer_id`),
              KEY `idx_vr_farm` (`farm_id`),
              KEY `idx_vr_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `insurance_assistance` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `reference_no` varchar(50) NOT NULL UNIQUE,
              `farmer_id` int(11) NOT NULL,
              `farm_id` int(11) NOT NULL,
              `livestock_id` int(11) DEFAULT NULL,
              `request_type` enum('Insurance Application','Death & Mortality Claim','Policy Renewal','Inquiry/Referral') NOT NULL DEFAULT 'Insurance Application',
              `livestock_type` varchar(100) NOT NULL,
              `head_count` int(11) NOT NULL DEFAULT 1,
              `cause_of_death` varchar(255) DEFAULT NULL,
              `date_of_incident` date DEFAULT NULL,
              `estimated_loss` decimal(12,2) NOT NULL DEFAULT 0.00,
              `document_url` varchar(500) DEFAULT NULL,
              `photo_url` varchar(500) DEFAULT NULL,
              `pcic_status` enum('Pending Verification','Endorsed to PCIC','Under PCIC Review','Claim Approved','Disbursed','Rejected') NOT NULL DEFAULT 'Pending Verification',
              `claim_tracking_code` varchar(100) DEFAULT NULL,
              `officer_notes` text DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NULL DEFAULT current_timestamp ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_ins_farmer` (`farmer_id`),
              KEY `idx_ins_farm` (`farm_id`),
              KEY `idx_ins_status` (`pcic_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `slaughter_declarations` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `declaration_no` varchar(50) NOT NULL UNIQUE,
              `farmer_id` int(11) NOT NULL,
              `farm_id` int(11) NOT NULL,
              `animal_tag_id` varchar(100) NOT NULL,
              `livestock_type` varchar(100) NOT NULL,
              `slaughter_type` enum('Household consumption slaughter','Slaughterhouse slaughter','Emergency slaughter','Suspected unauthorized slaughter') NOT NULL,
              `purpose` text NOT NULL,
              `slaughter_date` date NOT NULL,
              `location` varchar(255) NOT NULL,
              `document_url` varchar(500) DEFAULT NULL,
              `status` enum('Pending Review','Verified','Flagged/Investigating','Approved') NOT NULL DEFAULT 'Pending Review',
              `inspected_by` varchar(255) DEFAULT NULL,
              `inspector_notes` text DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_slg_farmer` (`farmer_id`),
              KEY `idx_slg_farm` (`farm_id`),
              KEY `idx_slg_tag` (`animal_tag_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `livestock_transfers` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `transfer_code` varchar(50) NOT NULL UNIQUE,
              `farmer_id` int(11) NOT NULL,
              `origin_farm_id` int(11) NOT NULL,
              `destination_farm_id` int(11) DEFAULT NULL,
              `destination_name` varchar(255) NOT NULL,
              `destination_address` text NOT NULL,
              `transfer_type` enum('Animal Sales','Transfer of Ownership','Farm-to-farm Transfer','Livestock Transport') NOT NULL,
              `animal_tag_id` varchar(100) NOT NULL,
              `livestock_type` varchar(100) NOT NULL,
              `transfer_date` date NOT NULL,
              `buyer_name` varchar(255) DEFAULT NULL,
              `buyer_contact` varchar(50) DEFAULT NULL,
              `transport_vehicle` varchar(100) DEFAULT NULL,
              `permit_url` varchar(500) DEFAULT NULL,
              `status` enum('Recorded','In Transit','Completed','Flagged') NOT NULL DEFAULT 'Recorded',
              `notes` text DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_mv_farmer` (`farmer_id`),
              KEY `idx_mv_origin` (`origin_farm_id`),
              KEY `idx_mv_tag` (`animal_tag_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Safe column upgrades on certificates
            try {
                $certCols = $pdo->query("SHOW COLUMNS FROM `certificates`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('farmer_id', $certCols, true)) {
                    $pdo->exec("ALTER TABLE `certificates` ADD COLUMN `farmer_id` INT(11) NULL AFTER `farm_id`");
                }
                if (!in_array('document_title', $certCols, true)) {
                    $pdo->exec("ALTER TABLE `certificates` ADD COLUMN `document_title` VARCHAR(255) NULL AFTER `cert_number`");
                }
                if (!in_array('uploaded_by', $certCols, true)) {
                    $pdo->exec("ALTER TABLE `certificates` ADD COLUMN `uploaded_by` ENUM('Official','Farmer') DEFAULT 'Official' AFTER `file_url`");
                }
                if (!in_array('verification_notes', $certCols, true)) {
                    $pdo->exec("ALTER TABLE `certificates` ADD COLUMN `verification_notes` TEXT NULL AFTER `uploaded_by`");
                }
                $pdo->exec("ALTER TABLE `certificates` MODIFY COLUMN `cert_type` VARCHAR(100) NOT NULL DEFAULT 'Ownership Certificate'");
            } catch (Throwable $e) {}

            // Safe column upgrades on livestock
            try {
                $lvCols = $pdo->query("SHOW COLUMNS FROM `livestock`")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('farming_type', $lvCols, true)) {
                    $pdo->exec("ALTER TABLE `livestock` ADD COLUMN `farming_type` ENUM('Backyard', 'Commercial') NOT NULL DEFAULT 'Backyard' AFTER `type`");
                }
                if (!in_array('dewormingStatus', $lvCols, true)) {
                    $pdo->exec("ALTER TABLE `livestock` ADD COLUMN `dewormingStatus` ENUM('Up to Date','Due Soon','Overdue','None') DEFAULT 'None' AFTER `vaccinationStatus`");
                }
                if (!in_array('lastCheckup', $lvCols, true)) {
                    $pdo->exec("ALTER TABLE `livestock` ADD COLUMN `lastCheckup` DATE NULL AFTER `dewormingStatus`");
                }
                if (!in_array('nextCheckup', $lvCols, true)) {
                    $pdo->exec("ALTER TABLE `livestock` ADD COLUMN `nextCheckup` DATE NULL AFTER `lastCheckup`");
                }
            } catch (Throwable $e) {}

        } catch (Throwable $t) {
            // Log or ignore if db permissions are restricted
            error_log("Schema auto-upgrade warning: " . $t->getMessage());
        }
    }
}


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
            $ensureCol('farms', 'application_type', "VARCHAR(50) DEFAULT 'New'");
            $ensureCol('farms', 'ownership', "VARCHAR(50) DEFAULT 'Private'");
            $ensureCol('incidents', 'gps_accuracy', "FLOAT NULL");
            $ensureCol('incidents', 'gps_source', "VARCHAR(10) NULL");
        } catch(Throwable $e) {
            error_log("Assistance tables check: " . $e->getMessage());
        }
    }
}
