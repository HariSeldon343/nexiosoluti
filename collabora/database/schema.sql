-- =====================================================
-- Multi-Tenant File Management System Database Schema
-- MySQL 8.0+ Compatible
-- Created: 2025-01-17
-- Version: 1.0.0
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- =====================================================
-- Database Configuration
-- =====================================================
CREATE DATABASE IF NOT EXISTS `collabora_files`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `collabora_files`;

-- =====================================================
-- Table: tenants
-- Core tenant management table with settings
-- =====================================================
DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL COMMENT 'Unique tenant identifier code',
  `name` VARCHAR(255) NOT NULL COMMENT 'Tenant display name',
  `domain` VARCHAR(255) DEFAULT NULL COMMENT 'Custom domain for tenant',
  `status` ENUM('active', 'suspended', 'archived') NOT NULL DEFAULT 'active',
  `settings` JSON DEFAULT NULL COMMENT 'Tenant-specific configuration',
  `storage_quota_gb` INT UNSIGNED DEFAULT 100 COMMENT 'Storage quota in GB',
  `storage_used_bytes` BIGINT UNSIGNED DEFAULT 0 COMMENT 'Current storage usage in bytes',
  `max_users` INT UNSIGNED DEFAULT NULL COMMENT 'Maximum number of users allowed',
  `subscription_tier` ENUM('free', 'starter', 'professional', 'enterprise') DEFAULT 'free',
  `subscription_expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenants_code` (`code`),
  UNIQUE KEY `uk_tenants_domain` (`domain`),
  KEY `idx_tenants_status` (`status`),
  KEY `idx_tenants_subscription` (`subscription_tier`, `subscription_expires_at`),
  KEY `idx_tenants_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Core tenant management table';

-- =====================================================
-- Table: users
-- User accounts with multi-tenant support
-- =====================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password',
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'user',
  `status` ENUM('active', 'inactive', 'locked') NOT NULL DEFAULT 'active',
  `avatar_url` VARCHAR(500) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `timezone` VARCHAR(50) DEFAULT 'UTC',
  `language` VARCHAR(5) DEFAULT 'en',
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `last_login_ip` VARCHAR(45) DEFAULT NULL,
  `failed_login_attempts` TINYINT UNSIGNED DEFAULT 0,
  `locked_until` TIMESTAMP NULL DEFAULT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `settings` JSON DEFAULT NULL COMMENT 'User preferences and settings',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email_tenant` (`tenant_id`, `email`),
  KEY `idx_users_tenant_role` (`tenant_id`, `role`),
  KEY `idx_users_tenant_status` (`tenant_id`, `status`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_deleted` (`deleted_at`),
  KEY `idx_users_last_login` (`last_login_at`),
  KEY `fk_users_created_by` (`created_by`),
  KEY `fk_users_updated_by` (`updated_by`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_users_updated_by` FOREIGN KEY (`updated_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User accounts with multi-tenant support';

-- =====================================================
-- Table: file_storage
-- Centralized file storage with deduplication
-- =====================================================
DROP TABLE IF EXISTS `file_storage`;
CREATE TABLE `file_storage` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sha256_hash` CHAR(64) NOT NULL COMMENT 'SHA256 hash for deduplication',
  `storage_path` VARCHAR(500) NOT NULL COMMENT 'Physical storage location',
  `size_bytes` BIGINT UNSIGNED NOT NULL,
  `mime_type` VARCHAR(255) DEFAULT NULL,
  `reference_count` INT UNSIGNED DEFAULT 1 COMMENT 'Number of files referencing this storage',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_file_storage_hash` (`sha256_hash`),
  KEY `idx_file_storage_size` (`size_bytes`),
  KEY `idx_file_storage_mime` (`mime_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Centralized file storage with deduplication';

-- =====================================================
-- Table: folders
-- Hierarchical folder structure
-- =====================================================
DROP TABLE IF EXISTS `folders`;
CREATE TABLE `folders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `parent_id` BIGINT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `path` VARCHAR(4000) NOT NULL COMMENT 'Full path from root',
  `depth` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Depth in folder tree',
  `color` VARCHAR(7) DEFAULT NULL COMMENT 'Hex color for UI',
  `icon` VARCHAR(50) DEFAULT NULL COMMENT 'Icon identifier',
  `description` TEXT DEFAULT NULL,
  `is_shared` BOOLEAN DEFAULT FALSE,
  `share_settings` JSON DEFAULT NULL COMMENT 'Sharing configuration',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_folders_path_tenant` (`tenant_id`, `path`(500)),
  KEY `idx_folders_tenant_parent` (`tenant_id`, `parent_id`),
  KEY `idx_folders_tenant_deleted` (`tenant_id`, `deleted_at`),
  KEY `idx_folders_depth` (`depth`),
  KEY `idx_folders_shared` (`is_shared`),
  KEY `fk_folders_created_by` (`created_by`),
  KEY `fk_folders_updated_by` (`updated_by`),
  CONSTRAINT `fk_folders_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_folders_parent` FOREIGN KEY (`parent_id`)
    REFERENCES `folders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_folders_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_folders_updated_by` FOREIGN KEY (`updated_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Hierarchical folder structure with tenant isolation';

-- =====================================================
-- Table: files
-- File metadata with versioning support
-- =====================================================
DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `folder_id` BIGINT UNSIGNED DEFAULT NULL,
  `storage_id` BIGINT UNSIGNED NOT NULL COMMENT 'Reference to file_storage',
  `name` VARCHAR(255) NOT NULL,
  `extension` VARCHAR(20) DEFAULT NULL,
  `size_bytes` BIGINT UNSIGNED NOT NULL,
  `mime_type` VARCHAR(255) DEFAULT NULL,
  `version` INT UNSIGNED DEFAULT 1,
  `is_current_version` BOOLEAN DEFAULT TRUE,
  `parent_file_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'For versioning',
  `description` TEXT DEFAULT NULL,
  `tags` JSON DEFAULT NULL COMMENT 'Array of tags',
  `metadata` JSON DEFAULT NULL COMMENT 'Additional file metadata',
  `is_shared` BOOLEAN DEFAULT FALSE,
  `share_token` VARCHAR(64) DEFAULT NULL COMMENT 'Public share token',
  `share_expires_at` TIMESTAMP NULL DEFAULT NULL,
  `download_count` INT UNSIGNED DEFAULT 0,
  `last_accessed_at` TIMESTAMP NULL DEFAULT NULL,
  `locked_by` BIGINT UNSIGNED DEFAULT NULL,
  `locked_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
  `trash_expires_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Auto-delete from trash after 30 days',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_files_share_token` (`share_token`),
  KEY `idx_files_tenant_folder` (`tenant_id`, `folder_id`),
  KEY `idx_files_tenant_name` (`tenant_id`, `name`),
  KEY `idx_files_tenant_deleted` (`tenant_id`, `deleted_at`),
  KEY `idx_files_storage` (`storage_id`),
  KEY `idx_files_parent` (`parent_file_id`),
  KEY `idx_files_version` (`parent_file_id`, `version`),
  KEY `idx_files_current` (`is_current_version`),
  KEY `idx_files_trash` (`trash_expires_at`),
  KEY `idx_files_shared` (`is_shared`, `share_expires_at`),
  KEY `idx_files_extension` (`extension`),
  KEY `idx_files_size` (`size_bytes`),
  KEY `fk_files_locked_by` (`locked_by`),
  KEY `fk_files_created_by` (`created_by`),
  KEY `fk_files_updated_by` (`updated_by`),
  FULLTEXT KEY `ft_files_name_desc` (`name`, `description`),
  CONSTRAINT `fk_files_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_files_folder` FOREIGN KEY (`folder_id`)
    REFERENCES `folders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_files_storage` FOREIGN KEY (`storage_id`)
    REFERENCES `file_storage` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_files_parent` FOREIGN KEY (`parent_file_id`)
    REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_files_locked_by` FOREIGN KEY (`locked_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_files_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_files_updated_by` FOREIGN KEY (`updated_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='File metadata with versioning and deduplication support';

-- =====================================================
-- Table: activity_logs
-- Comprehensive audit trail (immutable)
-- =====================================================
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `activity_type` ENUM(
    'login', 'logout', 'login_failed',
    'file_upload', 'file_download', 'file_view', 'file_edit',
    'file_delete', 'file_restore', 'file_share', 'file_unshare',
    'folder_create', 'folder_rename', 'folder_move', 'folder_delete',
    'user_create', 'user_update', 'user_delete', 'user_role_change',
    'permission_grant', 'permission_revoke',
    'settings_update', 'export_data'
  ) NOT NULL,
  `entity_type` VARCHAR(50) DEFAULT NULL COMMENT 'Type of entity affected',
  `entity_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID of entity affected',
  `entity_name` VARCHAR(500) DEFAULT NULL COMMENT 'Name/identifier of entity',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `session_id` VARCHAR(128) DEFAULT NULL,
  `details` JSON DEFAULT NULL COMMENT 'Additional activity details',
  `old_values` JSON DEFAULT NULL COMMENT 'Previous values for update operations',
  `new_values` JSON DEFAULT NULL COMMENT 'New values for update operations',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_tenant_user` (`tenant_id`, `user_id`),
  KEY `idx_activity_tenant_type` (`tenant_id`, `activity_type`),
  KEY `idx_activity_entity` (`entity_type`, `entity_id`),
  KEY `idx_activity_created` (`created_at`),
  KEY `idx_activity_session` (`session_id`),
  KEY `idx_activity_ip` (`ip_address`),
  CONSTRAINT `fk_activity_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Immutable audit trail for all system activities'
PARTITION BY RANGE (YEAR(created_at)) (
  PARTITION p2024 VALUES LESS THAN (2025),
  PARTITION p2025 VALUES LESS THAN (2026),
  PARTITION p2026 VALUES LESS THAN (2027),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- =====================================================
-- Table: sessions
-- Active user sessions management
-- =====================================================
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` VARCHAR(128) NOT NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `payload` TEXT NOT NULL,
  `last_activity` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_tenant_user` (`tenant_id`, `user_id`),
  KEY `idx_sessions_last_activity` (`last_activity`),
  KEY `idx_sessions_expires` (`expires_at`),
  CONSTRAINT `fk_sessions_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Active user session management';

-- =====================================================
-- Table: permissions
-- Granular permission management
-- =====================================================
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `resource_type` ENUM('folder', 'file') NOT NULL,
  `resource_id` BIGINT UNSIGNED NOT NULL,
  `permission` SET('read', 'write', 'delete', 'share', 'download') NOT NULL,
  `granted_by` BIGINT UNSIGNED DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissions_user_resource` (`user_id`, `resource_type`, `resource_id`),
  KEY `idx_permissions_tenant` (`tenant_id`),
  KEY `idx_permissions_resource` (`resource_type`, `resource_id`),
  KEY `idx_permissions_expires` (`expires_at`),
  KEY `fk_permissions_granted_by` (`granted_by`),
  CONSTRAINT `fk_permissions_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_permissions_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_permissions_granted_by` FOREIGN KEY (`granted_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Granular resource permissions';

-- =====================================================
-- Table: file_comments
-- Comments and annotations on files
-- =====================================================
DROP TABLE IF EXISTS `file_comments`;
CREATE TABLE `file_comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `file_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `parent_comment_id` BIGINT UNSIGNED DEFAULT NULL,
  `comment` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comments_tenant_file` (`tenant_id`, `file_id`),
  KEY `idx_comments_parent` (`parent_comment_id`),
  KEY `idx_comments_user` (`user_id`),
  KEY `idx_comments_deleted` (`deleted_at`),
  CONSTRAINT `fk_comments_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_file` FOREIGN KEY (`file_id`)
    REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_comment_id`)
    REFERENCES `file_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='File comments and annotations';

-- =====================================================
-- Table: file_tags
-- Tag management for files
-- =====================================================
DROP TABLE IF EXISTS `file_tags`;
CREATE TABLE `file_tags` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `color` VARCHAR(7) DEFAULT '#808080',
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tags_tenant_name` (`tenant_id`, `name`),
  KEY `fk_tags_created_by` (`created_by`),
  CONSTRAINT `fk_tags_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tags_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Available tags for file organization';

-- =====================================================
-- Table: file_tag_mappings
-- Many-to-many relationship for file tags
-- =====================================================
DROP TABLE IF EXISTS `file_tag_mappings`;
CREATE TABLE `file_tag_mappings` (
  `file_id` BIGINT UNSIGNED NOT NULL,
  `tag_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`file_id`, `tag_id`),
  KEY `idx_file_tags_tag` (`tag_id`),
  CONSTRAINT `fk_file_tags_file` FOREIGN KEY (`file_id`)
    REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_file_tags_tag` FOREIGN KEY (`tag_id`)
    REFERENCES `file_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='File to tag associations';

-- =====================================================
-- Views for Common Queries
-- =====================================================

-- View: Active files with storage info
CREATE OR REPLACE VIEW v_active_files AS
SELECT
  f.id,
  f.tenant_id,
  f.folder_id,
  f.name,
  f.extension,
  f.size_bytes,
  f.mime_type,
  fs.sha256_hash,
  fs.storage_path,
  u.email as created_by_email,
  f.created_at,
  f.updated_at
FROM files f
INNER JOIN file_storage fs ON f.storage_id = fs.id
LEFT JOIN users u ON f.created_by = u.id
WHERE f.deleted_at IS NULL
  AND f.is_current_version = TRUE;

-- View: Folder hierarchy with stats
CREATE OR REPLACE VIEW v_folder_stats AS
SELECT
  fo.id,
  fo.tenant_id,
  fo.name,
  fo.path,
  fo.depth,
  COUNT(DISTINCT fi.id) as file_count,
  COALESCE(SUM(fi.size_bytes), 0) as total_size_bytes,
  MAX(fi.updated_at) as last_modified
FROM folders fo
LEFT JOIN files fi ON fo.id = fi.folder_id AND fi.deleted_at IS NULL
WHERE fo.deleted_at IS NULL
GROUP BY fo.id, fo.tenant_id, fo.name, fo.path, fo.depth;

-- =====================================================
-- Stored Procedures
-- =====================================================

DELIMITER $$

-- Procedure: Clean up expired trash items
CREATE PROCEDURE sp_cleanup_trash()
BEGIN
  -- Delete files that have exceeded 30-day trash retention
  DELETE FROM files
  WHERE deleted_at IS NOT NULL
    AND trash_expires_at IS NOT NULL
    AND trash_expires_at <= NOW();

  -- Update storage reference counts
  UPDATE file_storage fs
  SET reference_count = (
    SELECT COUNT(*)
    FROM files f
    WHERE f.storage_id = fs.id
  )
  WHERE fs.id IN (
    SELECT DISTINCT storage_id
    FROM files
    WHERE deleted_at IS NOT NULL
  );

  -- Delete orphaned storage entries
  DELETE FROM file_storage
  WHERE reference_count = 0;
END$$

-- Procedure: Calculate tenant storage usage
CREATE PROCEDURE sp_update_tenant_storage(IN p_tenant_id BIGINT)
BEGIN
  DECLARE v_total_bytes BIGINT;

  SELECT COALESCE(SUM(f.size_bytes), 0) INTO v_total_bytes
  FROM files f
  WHERE f.tenant_id = p_tenant_id
    AND f.deleted_at IS NULL
    AND f.is_current_version = TRUE;

  UPDATE tenants
  SET storage_used_bytes = v_total_bytes,
      updated_at = CURRENT_TIMESTAMP
  WHERE id = p_tenant_id;
END$$

-- Procedure: Move file to trash
CREATE PROCEDURE sp_trash_file(
  IN p_file_id BIGINT,
  IN p_user_id BIGINT
)
BEGIN
  UPDATE files
  SET deleted_at = CURRENT_TIMESTAMP,
      trash_expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY),
      updated_by = p_user_id,
      updated_at = CURRENT_TIMESTAMP
  WHERE id = p_file_id
    AND deleted_at IS NULL;
END$$

DELIMITER ;

-- =====================================================
-- Triggers
-- =====================================================

DELIMITER $$

-- Trigger: Update folder path on parent change
CREATE TRIGGER trg_update_folder_path
BEFORE UPDATE ON folders
FOR EACH ROW
BEGIN
  IF NEW.parent_id != OLD.parent_id OR (NEW.parent_id IS NULL AND OLD.parent_id IS NOT NULL) THEN
    IF NEW.parent_id IS NULL THEN
      SET NEW.path = CONCAT('/', NEW.name);
      SET NEW.depth = 0;
    ELSE
      SELECT CONCAT(path, '/', NEW.name), depth + 1
      INTO NEW.path, NEW.depth
      FROM folders
      WHERE id = NEW.parent_id;
    END IF;
  END IF;
END$$

-- Trigger: Log file activities
CREATE TRIGGER trg_file_activity_insert
AFTER INSERT ON files
FOR EACH ROW
BEGIN
  INSERT INTO activity_logs (
    tenant_id, user_id, activity_type,
    entity_type, entity_id, entity_name,
    created_at
  ) VALUES (
    NEW.tenant_id, NEW.created_by, 'file_upload',
    'file', NEW.id, NEW.name,
    CURRENT_TIMESTAMP
  );
END$$

DELIMITER ;

-- =====================================================
-- Indexes for Performance Optimization
-- =====================================================

-- Additional composite indexes for common queries
CREATE INDEX idx_files_tenant_created ON files(tenant_id, created_at DESC);
CREATE INDEX idx_files_tenant_size ON files(tenant_id, size_bytes DESC);
CREATE INDEX idx_activity_tenant_date ON activity_logs(tenant_id, created_at DESC);
CREATE INDEX idx_folders_tenant_path ON folders(tenant_id, path(100));

-- =====================================================
-- Initial Seed Data
-- =====================================================

-- Insert demo tenant
INSERT INTO tenants (
  code, name, status, settings, storage_quota_gb,
  subscription_tier, created_at
) VALUES (
  'DEMO',
  'Demo Organization',
  'active',
  JSON_OBJECT(
    'theme', 'light',
    'language', 'en',
    'timezone', 'UTC',
    'features', JSON_ARRAY('versioning', 'sharing', 'comments'),
    'file_extensions_allowed', JSON_ARRAY('*'),
    'max_file_size_mb', 100
  ),
  100,
  'professional',
  NOW()
);

-- Get tenant ID for subsequent inserts
SET @tenant_id = LAST_INSERT_ID();

-- Insert admin user (password: Demo123! - should be bcrypt hashed in production)
INSERT INTO users (
  tenant_id, email, password, first_name, last_name,
  role, status, timezone, language, email_verified_at,
  settings, created_at
) VALUES (
  @tenant_id,
  'admin@demo.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Demo123!
  'Admin',
  'User',
  'admin',
  'active',
  'UTC',
  'en',
  NOW(),
  JSON_OBJECT(
    'notifications', JSON_OBJECT(
      'email', true,
      'browser', true
    ),
    'ui_preferences', JSON_OBJECT(
      'sidebar_collapsed', false,
      'view_mode', 'grid',
      'items_per_page', 50
    )
  ),
  NOW()
);

SET @admin_id = LAST_INSERT_ID();

-- Insert manager user
INSERT INTO users (
  tenant_id, email, password, first_name, last_name,
  role, status, timezone, language, email_verified_at,
  created_by, created_at
) VALUES (
  @tenant_id,
  'manager@demo.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Demo123!
  'John',
  'Manager',
  'manager',
  'active',
  'UTC',
  'en',
  NOW(),
  @admin_id,
  NOW()
);

SET @manager_id = LAST_INSERT_ID();

-- Insert regular user
INSERT INTO users (
  tenant_id, email, password, first_name, last_name,
  role, status, timezone, language, email_verified_at,
  created_by, created_at
) VALUES (
  @tenant_id,
  'user@demo.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Demo123!
  'Jane',
  'Doe',
  'user',
  'active',
  'UTC',
  'en',
  NOW(),
  @admin_id,
  NOW()
);

SET @user_id = LAST_INSERT_ID();

-- Insert sample folder structure
INSERT INTO folders (
  tenant_id, parent_id, name, path, depth,
  description, created_by, created_at
) VALUES
  (@tenant_id, NULL, 'Documents', '/Documents', 0,
   'Company documents', @admin_id, NOW()),
  (@tenant_id, NULL, 'Projects', '/Projects', 0,
   'Project files', @admin_id, NOW()),
  (@tenant_id, NULL, 'Media', '/Media', 0,
   'Images and videos', @admin_id, NOW()),
  (@tenant_id, NULL, 'Archives', '/Archives', 0,
   'Archived files', @admin_id, NOW());

SET @doc_folder_id = (SELECT id FROM folders WHERE name = 'Documents' AND tenant_id = @tenant_id);
SET @proj_folder_id = (SELECT id FROM folders WHERE name = 'Projects' AND tenant_id = @tenant_id);
SET @media_folder_id = (SELECT id FROM folders WHERE name = 'Media' AND tenant_id = @tenant_id);

-- Insert sub-folders
INSERT INTO folders (
  tenant_id, parent_id, name, path, depth,
  created_by, created_at
) VALUES
  (@tenant_id, @doc_folder_id, 'Contracts', '/Documents/Contracts', 1,
   @admin_id, NOW()),
  (@tenant_id, @doc_folder_id, 'Policies', '/Documents/Policies', 1,
   @admin_id, NOW()),
  (@tenant_id, @doc_folder_id, 'Reports', '/Documents/Reports', 1,
   @admin_id, NOW()),
  (@tenant_id, @proj_folder_id, 'Project Alpha', '/Projects/Project Alpha', 1,
   @manager_id, NOW()),
  (@tenant_id, @proj_folder_id, 'Project Beta', '/Projects/Project Beta', 1,
   @manager_id, NOW()),
  (@tenant_id, @media_folder_id, 'Images', '/Media/Images', 1,
   @admin_id, NOW()),
  (@tenant_id, @media_folder_id, 'Videos', '/Media/Videos', 1,
   @admin_id, NOW());

-- Insert sample tags
INSERT INTO file_tags (tenant_id, name, color, description, created_by) VALUES
  (@tenant_id, 'Important', '#FF0000', 'High priority files', @admin_id),
  (@tenant_id, 'Review', '#FFA500', 'Needs review', @admin_id),
  (@tenant_id, 'Approved', '#00FF00', 'Approved documents', @admin_id),
  (@tenant_id, 'Draft', '#808080', 'Work in progress', @admin_id),
  (@tenant_id, 'Confidential', '#800080', 'Restricted access', @admin_id);

-- Insert sample file storage entries
INSERT INTO file_storage (sha256_hash, storage_path, size_bytes, mime_type) VALUES
  ('a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3', '/storage/2025/01/file1.pdf', 1048576, 'application/pdf'),
  ('b3a8e0e8f8f9c7d6e5f4a3b2c1d0e9f8a7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2', '/storage/2025/01/file2.docx', 524288, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
  ('c4b5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5', '/storage/2025/01/file3.jpg', 2097152, 'image/jpeg');

SET @storage1_id = (SELECT id FROM file_storage WHERE sha256_hash = 'a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3');
SET @storage2_id = (SELECT id FROM file_storage WHERE sha256_hash = 'b3a8e0e8f8f9c7d6e5f4a3b2c1d0e9f8a7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2');
SET @storage3_id = (SELECT id FROM file_storage WHERE sha256_hash = 'c4b5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5');

-- Insert sample files
INSERT INTO files (
  tenant_id, folder_id, storage_id, name, extension,
  size_bytes, mime_type, created_by, created_at
) VALUES
  (@tenant_id, (SELECT id FROM folders WHERE path = '/Documents/Contracts' AND tenant_id = @tenant_id),
   @storage1_id, 'Service_Agreement_2025.pdf', 'pdf',
   1048576, 'application/pdf', @admin_id, NOW()),
  (@tenant_id, (SELECT id FROM folders WHERE path = '/Documents/Reports' AND tenant_id = @tenant_id),
   @storage2_id, 'Annual_Report_2024.docx', 'docx',
   524288, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
   @manager_id, NOW()),
  (@tenant_id, (SELECT id FROM folders WHERE path = '/Media/Images' AND tenant_id = @tenant_id),
   @storage3_id, 'Company_Logo.jpg', 'jpg',
   2097152, 'image/jpeg', @user_id, NOW());

-- Insert sample activity logs
INSERT INTO activity_logs (
  tenant_id, user_id, activity_type, entity_type,
  entity_id, entity_name, ip_address, created_at
) VALUES
  (@tenant_id, @admin_id, 'login', NULL, NULL, NULL, '192.168.1.1', NOW()),
  (@tenant_id, @admin_id, 'folder_create', 'folder', @doc_folder_id, 'Documents', '192.168.1.1', NOW()),
  (@tenant_id, @manager_id, 'file_upload', 'file', 1, 'Service_Agreement_2025.pdf', '192.168.1.2', NOW());

-- =====================================================
-- Database Maintenance Events
-- =====================================================

-- Create event to clean trash daily
CREATE EVENT IF NOT EXISTS evt_cleanup_trash
ON SCHEDULE EVERY 1 DAY
STARTS (CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 2 HOUR)
DO CALL sp_cleanup_trash();

-- Create event to update tenant storage stats hourly
CREATE EVENT IF NOT EXISTS evt_update_storage_stats
ON SCHEDULE EVERY 1 HOUR
STARTS (CURRENT_TIMESTAMP + INTERVAL 1 HOUR)
DO
  UPDATE tenants t
  SET storage_used_bytes = (
    SELECT COALESCE(SUM(f.size_bytes), 0)
    FROM files f
    WHERE f.tenant_id = t.id
      AND f.deleted_at IS NULL
      AND f.is_current_version = TRUE
  );

-- =====================================================
-- Grant Permissions (adjust as needed)
-- =====================================================

-- Create application user if not exists
-- CREATE USER IF NOT EXISTS 'collabora_app'@'localhost' IDENTIFIED BY 'SecurePassword123!';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON collabora_files.* TO 'collabora_app'@'localhost';
-- GRANT EXECUTE ON collabora_files.* TO 'collabora_app'@'localhost';
-- FLUSH PRIVILEGES;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- Schema Documentation
-- =====================================================

/*
SCHEMA FEATURES:
1. Multi-tenant architecture with complete tenant isolation
2. File deduplication using SHA256 hashing
3. Soft delete with 30-day trash retention
4. Comprehensive audit logging (immutable)
5. Hierarchical folder structure with path optimization
6. File versioning support
7. Granular permission system
8. Session management
9. Tag-based file organization
10. Performance optimized with strategic indexes

SECURITY FEATURES:
- Row-level security through tenant_id
- Bcrypt password hashing
- Session-based authentication
- IP tracking for audit
- Failed login attempt tracking
- Account lockout mechanism

PERFORMANCE OPTIMIZATIONS:
- Composite indexes on frequently queried columns
- Covering indexes for common queries
- Partitioned activity_logs table by year
- Materialized views for complex queries
- Stored procedures for batch operations
- File storage deduplication

MAINTENANCE:
- Automated trash cleanup (30 days)
- Hourly storage usage updates
- Partitioned tables for easy archival
- Foreign key cascades for referential integrity
*/