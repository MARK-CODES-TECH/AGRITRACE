CREATE DATABASE IF NOT EXISTS `myproject4`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `myproject4`;


CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `userId` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `tableName` varchar(50) DEFAULT NULL,
  `recordId` int(11) DEFAULT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `userAgent` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `createdAt` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `call_signals`
--

CREATE TABLE `call_signals` (
  `id` int(11) NOT NULL,
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  `type` enum('offer','answer','ice','hangup','reject','busy') NOT NULL,
  `call_type` enum('video','voice') DEFAULT 'video',
  `payload` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `farm_id` int(11) NOT NULL,
  `cert_number` varchar(100) NOT NULL,
  `cert_type` enum('Registration','Renewal','Health','Vaccination','Organic','Others') DEFAULT 'Registration',
  `issued_by` varchar(255) DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('Active','Expired','Revoked') DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `file_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `farmer_profiles`
--

CREATE TABLE `farmer_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `profile_pix` varchar(255) DEFAULT NULL,
  `gov_id` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `farms`
--

CREATE TABLE `farms` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `ownerId` int(11) NOT NULL,
  `ownerName` varchar(255) NOT NULL,
  `type` enum('Cattle','Swine','Poultry','Goat','Sheep','Carabao','Mixed') NOT NULL,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `area` decimal(8,2) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `application_type` enum('New','Renewal') DEFAULT 'New',
  `ownership` enum('Govt','Private') DEFAULT 'Private',
  `createdAt` timestamp NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `petitioner_surname` varchar(100) DEFAULT NULL,
  `petitioner_firstname` varchar(100) DEFAULT NULL,
  `pet_ext` varchar(10) DEFAULT NULL,
  `pet_sex` enum('Male','Female') DEFAULT NULL,
  `pet_house_no` varchar(100) DEFAULT NULL,
  `pet_street` varchar(255) DEFAULT NULL,
  `pet_barangay` varchar(100) DEFAULT NULL,
  `pet_municipality` varchar(100) DEFAULT NULL,
  `pet_province` varchar(100) DEFAULT 'Camarines Sur',
  `pet_dob` date DEFAULT NULL,
  `pet_pob` varchar(150) DEFAULT NULL,
  `pet_civil_status` enum('Single','Married','Widowed','Separated') DEFAULT NULL,
  `pet_spouse` varchar(255) DEFAULT NULL,
  `pet_mothers_name` varchar(255) DEFAULT NULL,
  `pet_education` enum('None','Elementary','High School','Vocational','College','Post Graduate') DEFAULT NULL,
  `pet_religion` varchar(100) DEFAULT NULL,
  `pet_male_members` tinyint(3) UNSIGNED DEFAULT 0,
  `pet_female_members` tinyint(3) UNSIGNED DEFAULT 0,
  `hh_head` enum('Yes','No') DEFAULT 'No',
  `hh_head_name` varchar(255) DEFAULT NULL,
  `pwd` enum('Yes','No') DEFAULT 'No',
  `fourps` enum('Yes','No') DEFAULT 'No',
  `indigenous` enum('Yes','No') DEFAULT 'No',
  `indigenous_specify` varchar(255) DEFAULT NULL,
  `has_gov_id` enum('Yes','No') DEFAULT 'No',
  `gov_id_number` varchar(100) DEFAULT NULL,
  `farmers_assoc` enum('Yes','No') DEFAULT 'No',
  `farmers_assoc_name` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_no` varchar(20) DEFAULT NULL,
  `main_livelihood` enum('Farmer','Farmworker/Laborer','Fisherfolk') DEFAULT 'Farmer',
  `farming_rice` tinyint(1) DEFAULT 0,
  `farming_corn` tinyint(1) DEFAULT 0,
  `farming_livestock` tinyint(1) DEFAULT 0,
  `farming_poultry` tinyint(1) DEFAULT 0,
  `farming_other` tinyint(1) DEFAULT 0,
  `farming_other_specify` varchar(255) DEFAULT NULL,
  `livestock_specify` varchar(255) DEFAULT NULL,
  `poultry_specify` varchar(255) DEFAULT NULL,
  `income_farming` decimal(12,2) DEFAULT 0.00,
  `income_non_farming` decimal(12,2) DEFAULT 0.00,
  `petitioner_ext` varchar(10) DEFAULT NULL,
  `prc_license` varchar(100) DEFAULT NULL,
  `prc_expiry` date DEFAULT NULL,
  `vet_name` varchar(255) DEFAULT NULL,
  `business_type` varchar(50) DEFAULT NULL,
  `facility_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`facility_types`)),
  `zip_code` varchar(10) DEFAULT '4410',
  `region` varchar(100) DEFAULT 'Region V — Bicol Region',
  `photo_url` varchar(500) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `farmId` int(11) DEFAULT NULL,
  `reporterId` int(11) DEFAULT NULL,
  `type` enum('Sick','Dead','Stray','Disease','Others') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('Pending','In Progress','Resolved','Closed') DEFAULT 'Pending',
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `livestock_type` varchar(100) DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `severity` varchar(50) DEFAULT 'Medium',
  `photoUrl` varchar(500) DEFAULT NULL,
  `videoUrl` varchar(500) DEFAULT NULL,
  `createdAt` timestamp NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolvedAt` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `livestock`
--

CREATE TABLE `livestock` (
  `id` int(11) NOT NULL,
  `farmId` int(11) NOT NULL,
  `type` enum('Cattle','Swine','Poultry','Goat','Sheep','Carabao') NOT NULL,
  `tagId` varchar(100) NOT NULL,
  `breed` varchar(100) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `weight` decimal(6,2) DEFAULT NULL,
  `healthStatus` enum('Healthy','Sick','Recovered','Deceased') DEFAULT 'Healthy',
  `vaccinationStatus` enum('Complete','Partial','None') DEFAULT 'None',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `qty` int(11) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `createdAt` timestamp NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `media_type` enum('image','video','audio','file') DEFAULT NULL,
  `media_thumb` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('INFO','WARNING','CRITICAL_ALERT','OUTBREAK_WARNING','FARM_APPROVED','FARM_REJECTED') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `incident_id` int(11) DEFAULT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `officer_permissions`
--

CREATE TABLE `officer_permissions` (
  `id` int(11) NOT NULL,
  `officer_id` int(11) NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `officer_profiles`
--

CREATE TABLE `officer_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `profile_pix` varchar(255) DEFAULT NULL,
  `gov_id` varchar(100) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `office` varchar(255) DEFAULT NULL,
  `assigned_region` varchar(255) DEFAULT NULL,
  `municipality` varchar(255) DEFAULT NULL,
  `province` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `outbreak_events`
--

CREATE TABLE `outbreak_events` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `lat` decimal(10,8) NOT NULL,
  `lng` decimal(11,8) NOT NULL,
  `radius_km` decimal(5,2) NOT NULL DEFAULT 10.00,
  `notified_farms` int(11) DEFAULT 0,
  `notified_officers` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `public_reports`
--

CREATE TABLE `public_reports` (
  `id` int(11) NOT NULL,
  `reportType` enum('Sick','Dead','Stray','Disease','Others') NOT NULL,
  `otherType` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `contactPhone` varchar(20) NOT NULL,
  `contactEmail` varchar(255) DEFAULT NULL,
  `idPhotoUrl` varchar(500) DEFAULT NULL,
  `facePhotoUrl` varchar(500) DEFAULT NULL,
  `mediaUrl` varchar(500) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('Pending','Reviewed','Resolved','Closed') DEFAULT 'Pending',
  `createdAt` timestamp NULL DEFAULT current_timestamp(),
  `reviewedAt` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `role` enum('Farmer','Agriculture Official','Admin') NOT NULL DEFAULT 'Farmer',
  `status` enum('Active','Inactive','Pending') DEFAULT 'Active',
  `emailVerified` tinyint(1) NOT NULL DEFAULT 0,
  `verifyToken` varchar(64) DEFAULT NULL,
  `verifyExpires` datetime DEFAULT NULL,
  `createdAt` timestamp NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resetToken` varchar(255) DEFAULT NULL,
  `resetExpires` datetime DEFAULT NULL,
  `otpCode` varchar(255) DEFAULT NULL,
  `otpExpires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `trg_audit_users_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    INSERT INTO `audit_log` (`userId`, `action`, `tableName`, `recordId`, `details`, `ipAddress`)
    VALUES (NEW.id, 'CREATE', 'users', NEW.id, JSON_OBJECT('email', NEW.email, 'role', NEW.role), 'SYSTEM');
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_audit_users_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    INSERT INTO `audit_log` (`userId`, `action`, `tableName`, `recordId`, `details`, `ipAddress`)
    VALUES (NEW.id, 'UPDATE', 'users', NEW.id, JSON_OBJECT('old_role', OLD.role, 'new_role', NEW.role), 'SYSTEM');
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `webrtc_signals`
--

CREATE TABLE `webrtc_signals` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `consumed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`userId`),
  ADD KEY `idx_audit_created` (`createdAt`),
  ADD KEY `idx_audit_action` (`action`);

--
-- Indexes for table `call_signals`
--
ALTER TABLE `call_signals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_signal_to` (`to_id`),
  ADD KEY `idx_signal_created` (`created_at`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cert_number` (`cert_number`),
  ADD KEY `idx_cert_farm` (`farm_id`),
  ADD KEY `idx_cert_status` (`status`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `farmer_profiles`
--
ALTER TABLE `farmer_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_farmer_profiles_user` (`user_id`),
  ADD KEY `idx_farmer_profiles_user` (`user_id`);

--
-- Indexes for table `farms`
--
ALTER TABLE `farms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_farms_owner` (`ownerId`),
  ADD KEY `idx_farms_status` (`status`),
  ADD KEY `idx_farms_owner_status` (`ownerId`,`status`),
  ADD KEY `fk_approved_by` (`approved_by`),
  ADD KEY `fk_rejected_by` (`rejected_by`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_incidents_farm` (`farmId`),
  ADD KEY `idx_incidents_reporter` (`reporterId`),
  ADD KEY `idx_incidents_status` (`status`),
  ADD KEY `idx_incidents_priority` (`priority`),
  ADD KEY `idx_incidents_created` (`createdAt`);

--
-- Indexes for table `livestock`
--
ALTER TABLE `livestock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_livestock_farm` (`farmId`),
  ADD KEY `idx_livestock_farm_type` (`farmId`,`type`),
  ADD KEY `idx_livestock_health` (`healthStatus`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_msg_sender` (`sender_id`),
  ADD KEY `idx_msg_receiver` (`receiver_id`),
  ADD KEY `idx_msg_convo` (`sender_id`,`receiver_id`),
  ADD KEY `idx_msg_created` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_type` (`type`),
  ADD KEY `idx_notifications_unread` (`user_id`,`is_read`),
  ADD KEY `idx_notifications_incident` (`incident_id`);

--
-- Indexes for table `officer_permissions`
--
ALTER TABLE `officer_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_officer_perm` (`officer_id`,`permission_key`),
  ADD KEY `idx_officer_permissions_officer` (`officer_id`);

--
-- Indexes for table `officer_profiles`
--
ALTER TABLE `officer_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_officer_profiles_user` (`user_id`),
  ADD KEY `idx_officer_profiles_user` (`user_id`);

--
-- Indexes for table `outbreak_events`
--
ALTER TABLE `outbreak_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_outbreak_incident` (`incident_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_reset` (`user_id`),
  ADD KEY `idx_user_expires` (`user_id`,`expires_at`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `public_reports`
--
ALTER TABLE `public_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_public_status` (`status`),
  ADD KEY `idx_public_created` (`createdAt`),
  ADD KEY `idx_public_status_created` (`status`,`createdAt`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_users_email` (`email`),
  ADD KEY `idx_users_role_status` (`role`,`status`);

--
-- Indexes for table `webrtc_signals`
--
ALTER TABLE `webrtc_signals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recv_consumed` (`receiver_id`,`consumed`),
  ADD KEY `idx_created` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `call_signals`
--
ALTER TABLE `call_signals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `farmer_profiles`
--
ALTER TABLE `farmer_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `farms`
--
ALTER TABLE `farms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `livestock`
--
ALTER TABLE `livestock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `officer_permissions`
--
ALTER TABLE `officer_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `officer_profiles`
--
ALTER TABLE `officer_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `outbreak_events`
--
ALTER TABLE `outbreak_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `public_reports`
--
ALTER TABLE `public_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `webrtc_signals`
--
ALTER TABLE `webrtc_signals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `fk_cert_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `farmer_profiles`
--
ALTER TABLE `farmer_profiles`
  ADD CONSTRAINT `fk_farmer_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `farms`
--
ALTER TABLE `farms`
  ADD CONSTRAINT `fk_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_farms_owner` FOREIGN KEY (`ownerId`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `incidents`
--
ALTER TABLE `incidents`
  ADD CONSTRAINT `fk_incidents_farm` FOREIGN KEY (`farmId`) REFERENCES `farms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_incidents_reporter` FOREIGN KEY (`reporterId`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `livestock`
--
ALTER TABLE `livestock`
  ADD CONSTRAINT `fk_livestock_farm` FOREIGN KEY (`farmId`) REFERENCES `farms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `officer_permissions`
--
ALTER TABLE `officer_permissions`
  ADD CONSTRAINT `fk_officer_permissions_user` FOREIGN KEY (`officer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `officer_profiles`
--
ALTER TABLE `officer_profiles`
  ADD CONSTRAINT `fk_officer_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `outbreak_events`
--
ALTER TABLE `outbreak_events`
  ADD CONSTRAINT `fk_outbreak_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;
COMMIT;

ALTER TABLE `farms` 
ADD COLUMN `application_type` ENUM('New', 'Renewal') DEFAULT 'New' AFTER `status`;

ALTER TABLE `farms` 
ADD COLUMN `ownership` ENUM('Govt', 'Private') DEFAULT 'Private' AFTER `application_type`;



--
-- Supplementary Tables: farm_registrations, rsbsa_enrollments, pcic_insurance_applications, password_resets
--
CREATE TABLE IF NOT EXISTS `farm_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` int(11) NOT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `farm_name` varchar(255) DEFAULT NULL,
  `farm_address` text DEFAULT NULL,
  `form_data` longtext DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fr_farmer` (`farmer_id`),
  KEY `idx_fr_farm` (`farm_id`),
  KEY `idx_fr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rsbsa_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` int(11) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `applicant_name` varchar(255) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `municipality` varchar(100) DEFAULT NULL,
  `livelihood_category` varchar(100) DEFAULT NULL,
  `form_data` longtext DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rsbsa_farmer` (`farmer_id`),
  KEY `idx_rsbsa_ref` (`reference_no`),
  KEY `idx_rsbsa_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pcic_insurance_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmer_id` int(11) NOT NULL,
  `application_no` varchar(100) DEFAULT NULL,
  `applicant_name` varchar(255) DEFAULT NULL,
  `farm_location` varchar(255) DEFAULT NULL,
  `animal_type` varchar(100) DEFAULT NULL,
  `head_count` int(11) DEFAULT 1,
  `form_data` longtext DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pcic_farmer` (`farmer_id`),
  KEY `idx_pcic_app` (`application_no`),
  KEY `idx_pcic_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `otp` varchar(10) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pr_user` (`user_id`),
  KEY `idx_pr_email` (`email`),
  KEY `idx_pr_token` (`token`),
  KEY `idx_pr_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
