-- =====================================================
-- Authentication System V2 Migration
-- Role-based access control with multi-tenant support
-- Created: 2025-01-17
-- Version: 2.0.0
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

USE `collabora_files`;

-- =====================================================
-- Drop old constraints and modify users table
-- =====================================================
ALTER TABLE `users`
  DROP FOREIGN KEY IF EXISTS `fk_users_tenant`,
  DROP INDEX IF EXISTS `uk_users_email_tenant`;

-- Modify users table for new role system
ALTER TABLE `users`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NULL COMMENT 'Primary tenant ID (NULL for admins)',
  MODIFY COLUMN `role` ENUM('admin', 'special_user', 'standard_user') NOT NULL DEFAULT 'standard_user',
  ADD COLUMN `is_system_admin` BOOLEAN DEFAULT FALSE COMMENT 'System-wide admin flag' AFTER `role`,
  ADD COLUMN `two_factor_secret` VARCHAR(255) DEFAULT NULL AFTER `locked_until`,
  ADD COLUMN `two_factor_enabled` BOOLEAN DEFAULT FALSE AFTER `two_factor_secret`;

-- Add unique constraint for email (globally unique now)
ALTER TABLE `users`
  ADD UNIQUE KEY `uk_users_email` (`email`);

-- =====================================================
-- Table: user_tenant_associations
-- Maps users to multiple tenants (for special users)
-- =====================================================
DROP TABLE IF EXISTS `user_tenant_associations`;
CREATE TABLE `user_tenant_associations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `role_in_tenant` ENUM('owner', 'admin', 'manager', 'user') NOT NULL DEFAULT 'user',
  `is_primary` BOOLEAN DEFAULT FALSE COMMENT 'Primary tenant for the user',
  `permissions` JSON DEFAULT NULL COMMENT 'Tenant-specific permissions',
  `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `invited_by` BIGINT UNSIGNED DEFAULT NULL,
  `last_accessed_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_tenant` (`user_id`, `tenant_id`),
  KEY `idx_user_associations` (`user_id`, `is_primary`),
  KEY `idx_tenant_associations` (`tenant_id`),
  KEY `idx_last_accessed` (`last_accessed_at`),
  KEY `fk_invited_by` (`invited_by`),
  CONSTRAINT `fk_uta_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_uta_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_uta_invited_by` FOREIGN KEY (`invited_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Maps users to multiple tenants for role-based access';

-- =====================================================
-- Table: user_sessions
-- Active user sessions with tenant context
-- =====================================================
DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` VARCHAR(128) NOT NULL COMMENT 'PHP Session ID',
  `user_id` BIGINT UNSIGNED NOT NULL,
  `current_tenant_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Currently active tenant',
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data` JSON DEFAULT NULL COMMENT 'Session data',
  PRIMARY KEY (`id`),
  KEY `idx_user_sessions` (`user_id`),
  KEY `idx_tenant_sessions` (`current_tenant_id`),
  KEY `idx_last_activity` (`last_activity`),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_session_tenant` FOREIGN KEY (`current_tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Active user sessions with tenant context';

-- =====================================================
-- Table: activity_logs
-- Comprehensive activity logging for security
-- =====================================================
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) DEFAULT NULL,
  `entity_id` BIGINT UNSIGNED DEFAULT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `session_id` VARCHAR(128) DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_user` (`user_id`),
  KEY `idx_logs_tenant` (`tenant_id`),
  KEY `idx_logs_action` (`action`),
  KEY `idx_logs_entity` (`entity_type`, `entity_id`),
  KEY `idx_logs_created` (`created_at`),
  KEY `idx_logs_session` (`session_id`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_logs_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Comprehensive activity logging';

-- =====================================================
-- Table: permission_sets
-- Define custom permission sets for roles
-- =====================================================
DROP TABLE IF EXISTS `permission_sets`;
CREATE TABLE `permission_sets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `permissions` JSON NOT NULL COMMENT 'Array of permission strings',
  `is_system` BOOLEAN DEFAULT FALSE COMMENT 'System-defined permission set',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permission_name` (`name`),
  KEY `idx_permission_system` (`is_system`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Define custom permission sets';

-- =====================================================
-- Remove tenant_code requirement from tenants table
-- =====================================================
ALTER TABLE `tenants`
  MODIFY COLUMN `code` VARCHAR(20) DEFAULT NULL COMMENT 'Optional tenant code for legacy support',
  DROP INDEX IF EXISTS `uk_tenants_code`,
  ADD KEY `idx_tenants_code` (`code`);

-- =====================================================
-- Insert default admin user
-- =====================================================
INSERT INTO `users` (
  `email`,
  `password`,
  `first_name`,
  `last_name`,
  `role`,
  `is_system_admin`,
  `status`,
  `email_verified_at`,
  `created_at`
) VALUES (
  'asamodeo@fortibyte.it',
  '$2y$12$' || SUBSTRING(SHA2(CONCAT('Ricord@1991', DATE_FORMAT(NOW(), '%Y%m')), 256), 1, 53),
  'Admin',
  'System',
  'admin',
  TRUE,
  'active',
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE
  `is_system_admin` = TRUE,
  `role` = 'admin';

-- =====================================================
-- Insert default permission sets
-- =====================================================
INSERT INTO `permission_sets` (`name`, `description`, `permissions`, `is_system`) VALUES
('admin', 'Full system administration', JSON_ARRAY(
  'users.*',
  'tenants.*',
  'files.*',
  'settings.*',
  'logs.view',
  'system.*'
), TRUE),
('special_user', 'Multi-tenant user permissions', JSON_ARRAY(
  'tenants.switch',
  'tenants.view',
  'files.*',
  'users.view',
  'users.edit_own'
), TRUE),
('standard_user', 'Single tenant user permissions', JSON_ARRAY(
  'files.create',
  'files.read',
  'files.update_own',
  'files.delete_own',
  'users.view_own',
  'users.edit_own'
), TRUE);

-- =====================================================
-- Cleanup: Remove old sessions after migration
-- =====================================================
DELETE FROM `user_sessions` WHERE `last_activity` < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- =====================================================
-- Create stored procedure for session cleanup
-- =====================================================
DELIMITER $$
DROP PROCEDURE IF EXISTS `cleanup_expired_sessions`$$
CREATE PROCEDURE `cleanup_expired_sessions`()
BEGIN
  DELETE FROM `user_sessions`
  WHERE `last_activity` < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END$$
DELIMITER ;

-- =====================================================
-- Create event for automatic session cleanup
-- =====================================================
DROP EVENT IF EXISTS `auto_cleanup_sessions`;
CREATE EVENT IF NOT EXISTS `auto_cleanup_sessions`
  ON SCHEDULE EVERY 1 HOUR
  DO CALL cleanup_expired_sessions();

SET FOREIGN_KEY_CHECKS = 1;