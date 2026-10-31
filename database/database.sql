-- AgriTrace+ clean database bootstrap
CREATE DATABASE IF NOT EXISTS `myproject4` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `myproject4`;

-- Users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `role` enum('Farmer','Agriculture Official','Admin') NOT NULL DEFAULT 'Farmer',
  `status` enum('Active','Inactive','Pending') DEFAULT 'Active',
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resetToken` varchar(255) DEFAULT NULL,
  `resetExpires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role_status` (`role`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `farms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `ownerId` int(11) NOT NULL,
  `ownerName` varchar(255) NOT NULL,
  `type` enum('Cattle','Swine','Poultry','Goat','Mixed') NOT NULL,
  `address` text,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `area` decimal(8,2) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `petitioner_surname` VARCHAR(100) DEFAULT NULL,
  `petitioner_firstname` VARCHAR(100) DEFAULT NULL,
  `petitioner_ext` VARCHAR(10) DEFAULT NULL,
  `prc_license` VARCHAR(100) DEFAULT NULL,
  `prc_expiry` DATE DEFAULT NULL,
  `vet_name` VARCHAR(255) DEFAULT NULL,
  `business_type` VARCHAR(50) DEFAULT 'Sole Proprietor',
  `facility_types` JSON DEFAULT NULL,
  `zip_code` VARCHAR(10) DEFAULT '4410',
  `region` VARCHAR(100) DEFAULT 'Region V — Bicol Region',
  `photo_url` VARCHAR(500) DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `approved_by` INT NULL,
  `approved_at` TIMESTAMP NULL,
  `rejected_by` INT NULL,
  `rejected_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_farms_owner` (`ownerId`),
  KEY `idx_farms_status` (`status`),
  KEY `idx_farms_owner_status` (`ownerId`,`status`),
  KEY `idx_farms_approved_by` (`approved_by`),
  KEY `idx_farms_rejected_by` (`rejected_by`),
  CONSTRAINT `fk_farms_owner` FOREIGN KEY (`ownerId`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_farms_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_farms_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Livestock
CREATE TABLE IF NOT EXISTS `livestock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farmId` int(11) NOT NULL,
  `type` enum('Cattle','Swine','Poultry','Goat','Sheep') NOT NULL,
  `tagId` varchar(100) NOT NULL,
  `breed` varchar(100) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `weight` decimal(6,2) DEFAULT NULL,
  `healthStatus` enum('Healthy','Sick','Recovered','Deceased') DEFAULT 'Healthy',
  `vaccinationStatus` enum('Complete','Partial','None') DEFAULT 'None',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `qty` int(11) DEFAULT 1,
  `notes` text,
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_livestock_farm` (`farmId`),
  KEY `idx_livestock_farm_type` (`farmId`,`type`),
  KEY `idx_livestock_health` (`healthStatus`),
  CONSTRAINT `fk_livestock_farm` FOREIGN KEY (`farmId`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Incidents
CREATE TABLE IF NOT EXISTS `incidents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolvedAt` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_incidents_farm` (`farmId`),
  KEY `idx_incidents_reporter` (`reporterId`),
  KEY `idx_incidents_status` (`status`),
  KEY `idx_incidents_priority` (`priority`),
  KEY `idx_incidents_created` (`createdAt`),
  CONSTRAINT `fk_incidents_farm` FOREIGN KEY (`farmId`) REFERENCES `farms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_incidents_reporter` FOREIGN KEY (`reporterId`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('INFO','WARNING','CRITICAL_ALERT','OUTBREAK_WARNING','FARM_APPROVED','FARM_REJECTED') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `incident_id` int(11) DEFAULT NULL,
  `farm_id` int(11) DEFAULT NULL,
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_type` (`type`),
  KEY `idx_notifications_unread` (`user_id`,`is_read`),
  KEY `idx_notifications_incident` (`incident_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Outbreak events log
CREATE TABLE IF NOT EXISTS `outbreak_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incident_id` int(11) NOT NULL,
  `lat` decimal(10,8) NOT NULL,
  `lng` decimal(11,8) NOT NULL,
  `radius_km` decimal(5,2) NOT NULL DEFAULT 10.00,
  `notified_farms` int(11) DEFAULT 0,
  `notified_officers` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_outbreak_incident` (`incident_id`),
  CONSTRAINT `fk_outbreak_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Public reports
CREATE TABLE IF NOT EXISTS `public_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewedAt` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_public_status` (`status`),
  KEY `idx_public_created` (`createdAt`),
  KEY `idx_public_status_created` (`status`,`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `tableName` varchar(50) DEFAULT NULL,
  `recordId` int(11) DEFAULT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `userAgent` text,
  `details` json DEFAULT NULL,
  `createdAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`userId`),
  KEY `idx_audit_created` (`createdAt`),
  KEY `idx_audit_action` (`action`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Officer permissions
CREATE TABLE IF NOT EXISTS `officer_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `officer_id` int(11) NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_officer_perm` (`officer_id`,`permission_key`),
  KEY `idx_officer_permissions_officer` (`officer_id`),
  CONSTRAINT `fk_officer_permissions_user` FOREIGN KEY (`officer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Officer profiles
CREATE TABLE IF NOT EXISTS `officer_profiles` (
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
  UNIQUE KEY `uk_officer_profiles_user` (`user_id`),
  KEY `idx_officer_profiles_user` (`user_id`),
  CONSTRAINT `fk_officer_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `farmer_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `profile_pix` varchar(255) DEFAULT NULL,
  `gov_id` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_farmer_profiles_user` (`user_id`),
  KEY `idx_farmer_profiles_user` (`user_id`),
  CONSTRAINT `fk_farmer_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_id` int(11) NOT NULL,
  `cert_number` varchar(100) NOT NULL,
  `cert_type` enum('Registration','Health','Vaccination','Organic','Others') DEFAULT 'Registration',
  `issued_by` varchar(255) DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('Active','Expired','Revoked') DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `file_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cert_number` (`cert_number`),
  KEY `idx_cert_farm` (`farm_id`),
  KEY `idx_cert_status` (`status`),
  CONSTRAINT `fk_cert_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Baseline admin user
INSERT IGNORE INTO `users` (`id`, `firstName`, `lastName`, `email`, `password`, `mobile`, `role`, `status`)
VALUES
(1, 'System', 'Admin', 'admin@agritrace.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+63 999 000 0001', 'Admin', 'Active');

-- Audit triggers
DROP TRIGGER IF EXISTS `trg_audit_users_insert`;
DROP TRIGGER IF EXISTS `trg_audit_users_update`;

DELIMITER //
CREATE TRIGGER `trg_audit_users_insert`
AFTER INSERT ON `users`
FOR EACH ROW
BEGIN
    INSERT INTO `audit_log` (`userId`, `action`, `tableName`, `recordId`, `details`, `ipAddress`)
    VALUES (NEW.id, 'CREATE', 'users', NEW.id, JSON_OBJECT('email', NEW.email, 'role', NEW.role), 'SYSTEM');
END//

CREATE TRIGGER `trg_audit_users_update`
AFTER UPDATE ON `users`
FOR EACH ROW
BEGIN
    INSERT INTO `audit_log` (`userId`, `action`, `tableName`, `recordId`, `details`, `ipAddress`)
    VALUES (NEW.id, 'UPDATE', 'users', NEW.id, JSON_OBJECT('old_role', OLD.role, 'new_role', NEW.role), 'SYSTEM');
END//
DELIMITER ;
