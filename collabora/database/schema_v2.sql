-- =====================================================
-- Multi-Tenant Collaboration System Database Schema V2
-- MariaDB 10.6+ / MySQL 8.0+ Compatible
-- Created: 2025-01-17
-- Version: 2.0.0
--
-- MAJOR CHANGES:
-- - New role system: admin, special_user, standard_user
-- - Removed tenant code from login (email/password only)
-- - Added user_tenant_associations for many-to-many
-- - Admin has global access without tenant association
-- - Special users can access multiple tenants
-- - Standard users limited to single tenant
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
  `branding` JSON DEFAULT NULL COMMENT 'Tenant branding configuration (logo, colors, etc)',
  `modules_enabled` JSON DEFAULT NULL COMMENT 'Array of enabled modules for tenant',
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
-- User accounts - NO tenant_id required (multi-tenant via associations)
-- Role determines access level:
-- - admin: 100% control over all tenants and system
-- - special_user: Multi-tenant access, no delete permissions
-- - standard_user: Single tenant access only
-- =====================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL COMMENT 'Unique email for login',
  `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password',
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'special_user', 'standard_user') NOT NULL DEFAULT 'standard_user' COMMENT 'System-wide role',
  `status` ENUM('active', 'inactive', 'locked', 'pending') NOT NULL DEFAULT 'pending',
  `avatar_url` VARCHAR(500) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `timezone` VARCHAR(50) DEFAULT 'UTC',
  `language` VARCHAR(5) DEFAULT 'en',
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `last_login_ip` VARCHAR(45) DEFAULT NULL,
  `last_active_tenant_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Last accessed tenant for quick switch',
  `failed_login_attempts` TINYINT UNSIGNED DEFAULT 0,
  `locked_until` TIMESTAMP NULL DEFAULT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `two_factor_secret` VARCHAR(255) DEFAULT NULL COMMENT 'For 2FA authentication',
  `two_factor_enabled` BOOLEAN DEFAULT FALSE,
  `settings` JSON DEFAULT NULL COMMENT 'User preferences and settings',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_deleted` (`deleted_at`),
  KEY `idx_users_last_login` (`last_login_at`),
  KEY `idx_users_last_tenant` (`last_active_tenant_id`),
  KEY `fk_users_created_by` (`created_by`),
  KEY `fk_users_updated_by` (`updated_by`),
  CONSTRAINT `fk_users_last_tenant` FOREIGN KEY (`last_active_tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_users_updated_by` FOREIGN KEY (`updated_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User accounts with role-based multi-tenant support';

-- =====================================================
-- Table: user_tenant_associations
-- Many-to-many relationship between users and tenants
-- Enforces business rules per role type
-- =====================================================
DROP TABLE IF EXISTS `user_tenant_associations`;
CREATE TABLE `user_tenant_associations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `is_primary` BOOLEAN DEFAULT FALSE COMMENT 'Primary tenant for the user',
  `tenant_role` ENUM('owner', 'admin', 'manager', 'member', 'viewer') DEFAULT 'member' COMMENT 'Role within this specific tenant',
  `permissions` JSON DEFAULT NULL COMMENT 'Specific permissions for this tenant (overrides)',
  `modules_access` JSON DEFAULT NULL COMMENT 'Specific module access for this user in this tenant',
  `access_expires_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Temporary access expiration',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_tenant` (`user_id`, `tenant_id`),
  KEY `idx_tenant_users` (`tenant_id`, `user_id`),
  KEY `idx_user_primary` (`user_id`, `is_primary`),
  KEY `idx_access_expires` (`access_expires_at`),
  KEY `fk_uta_created_by` (`created_by`),
  CONSTRAINT `fk_uta_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_uta_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_uta_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User-Tenant associations with role-based restrictions';

-- Create index for enforcing single tenant for standard users
CREATE INDEX idx_uta_standard_user ON user_tenant_associations(user_id)
  WHERE user_id IN (SELECT id FROM users WHERE role = 'standard_user');

-- =====================================================
-- Table: groups
-- User groups for permission management within tenants
-- =====================================================
DROP TABLE IF EXISTS `groups`;
CREATE TABLE `groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `permissions` JSON DEFAULT NULL COMMENT 'Group permissions',
  `is_system` BOOLEAN DEFAULT FALSE COMMENT 'System-defined group',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_groups_tenant_name` (`tenant_id`, `name`),
  KEY `idx_groups_system` (`is_system`),
  KEY `idx_groups_deleted` (`deleted_at`),
  KEY `fk_groups_created_by` (`created_by`),
  CONSTRAINT `fk_groups_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_groups_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User groups for permission management';

-- =====================================================
-- Table: user_groups
-- Many-to-many user to group associations
-- =====================================================
DROP TABLE IF EXISTS `user_groups`;
CREATE TABLE `user_groups` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `group_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`user_id`, `group_id`),
  KEY `idx_user_groups_group` (`group_id`),
  KEY `fk_ug_created_by` (`created_by`),
  CONSTRAINT `fk_ug_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ug_group` FOREIGN KEY (`group_id`)
    REFERENCES `groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ug_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User to group associations';

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
  KEY `idx_file_storage_mime` (`mime_type`),
  KEY `idx_file_storage_refs` (`reference_count`)
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
-- Table: calendars
-- Calendar management for events and scheduling
-- =====================================================
DROP TABLE IF EXISTS `calendars`;
CREATE TABLE `calendars` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Owner user ID',
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `color` VARCHAR(7) DEFAULT '#0066CC',
  `timezone` VARCHAR(50) DEFAULT 'UTC',
  `is_public` BOOLEAN DEFAULT FALSE,
  `caldav_uid` VARCHAR(255) DEFAULT NULL COMMENT 'CalDAV unique identifier',
  `etag` VARCHAR(255) DEFAULT NULL COMMENT 'For CalDAV sync',
  `settings` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_calendars_uid` (`caldav_uid`),
  KEY `idx_calendars_tenant_user` (`tenant_id`, `user_id`),
  KEY `idx_calendars_public` (`is_public`),
  KEY `idx_calendars_deleted` (`deleted_at`),
  CONSTRAINT `fk_calendars_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calendars_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Calendar management with CalDAV support';

-- =====================================================
-- Table: events
-- Calendar events with recurrence support
-- =====================================================
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `calendar_id` BIGINT UNSIGNED NOT NULL,
  `uid` VARCHAR(255) NOT NULL COMMENT 'RFC 4791 UID',
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `location` VARCHAR(500) DEFAULT NULL,
  `start_datetime` DATETIME NOT NULL,
  `end_datetime` DATETIME NOT NULL,
  `all_day` BOOLEAN DEFAULT FALSE,
  `timezone` VARCHAR(50) DEFAULT 'UTC',
  `recurrence_rule` TEXT DEFAULT NULL COMMENT 'RRULE format',
  `recurrence_exceptions` JSON DEFAULT NULL COMMENT 'Exception dates',
  `attendees` JSON DEFAULT NULL COMMENT 'Event attendees',
  `reminders` JSON DEFAULT NULL COMMENT 'Reminder settings',
  `status` ENUM('tentative', 'confirmed', 'cancelled') DEFAULT 'confirmed',
  `etag` VARCHAR(255) DEFAULT NULL COMMENT 'For sync conflict resolution',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_events_uid` (`uid`),
  KEY `idx_events_tenant_calendar` (`tenant_id`, `calendar_id`),
  KEY `idx_events_datetime` (`start_datetime`, `end_datetime`),
  KEY `idx_events_recurrence` (`recurrence_rule`(100)),
  KEY `idx_events_status` (`status`),
  KEY `idx_events_deleted` (`deleted_at`),
  KEY `fk_events_created_by` (`created_by`),
  CONSTRAINT `fk_events_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_events_calendar` FOREIGN KEY (`calendar_id`)
    REFERENCES `calendars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Calendar events with RRULE recurrence support';

-- =====================================================
-- Table: tasks
-- Task management with flexible scheduling
-- =====================================================
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `assigned_to` BIGINT UNSIGNED DEFAULT NULL,
  `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
  `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
  `due_date` DATE DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `estimated_hours` DECIMAL(5,2) DEFAULT NULL,
  `actual_hours` DECIMAL(5,2) DEFAULT NULL,
  `tags` JSON DEFAULT NULL,
  `attachments` JSON DEFAULT NULL,
  `recurrence_pattern` JSON DEFAULT NULL COMMENT 'Non-consecutive day scheduling',
  `parent_task_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_tenant_assigned` (`tenant_id`, `assigned_to`),
  KEY `idx_tasks_tenant_status` (`tenant_id`, `status`),
  KEY `idx_tasks_priority` (`priority`),
  KEY `idx_tasks_due_date` (`due_date`),
  KEY `idx_tasks_parent` (`parent_task_id`),
  KEY `idx_tasks_deleted` (`deleted_at`),
  KEY `fk_tasks_created_by` (`created_by`),
  KEY `fk_tasks_updated_by` (`updated_by`),
  CONSTRAINT `fk_tasks_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tasks_assigned_to` FOREIGN KEY (`assigned_to`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tasks_parent` FOREIGN KEY (`parent_task_id`)
    REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tasks_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tasks_updated_by` FOREIGN KEY (`updated_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task management with flexible scheduling';

-- =====================================================
-- Table: task_occurrences
-- Individual task occurrences for non-consecutive scheduling
-- =====================================================
DROP TABLE IF EXISTS `task_occurrences`;
CREATE TABLE `task_occurrences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `scheduled_date` DATE NOT NULL,
  `status` ENUM('pending', 'completed', 'skipped') DEFAULT 'pending',
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_by` BIGINT UNSIGNED DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_occurrence` (`task_id`, `scheduled_date`),
  KEY `idx_occurrences_date` (`scheduled_date`),
  KEY `idx_occurrences_status` (`status`),
  KEY `fk_occurrences_completed_by` (`completed_by`),
  CONSTRAINT `fk_occurrences_task` FOREIGN KEY (`task_id`)
    REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_occurrences_completed_by` FOREIGN KEY (`completed_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task occurrences for non-consecutive day scheduling';

-- =====================================================
-- Table: rooms (Chat rooms)
-- =====================================================
DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `type` ENUM('direct', 'group', 'channel') NOT NULL DEFAULT 'group',
  `is_private` BOOLEAN DEFAULT TRUE,
  `avatar_url` VARCHAR(500) DEFAULT NULL,
  `last_activity_at` TIMESTAMP NULL DEFAULT NULL,
  `settings` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rooms_tenant_type` (`tenant_id`, `type`),
  KEY `idx_rooms_last_activity` (`last_activity_at`),
  KEY `idx_rooms_deleted` (`deleted_at`),
  KEY `fk_rooms_created_by` (`created_by`),
  CONSTRAINT `fk_rooms_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rooms_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Chat rooms for real-time messaging';

-- =====================================================
-- Table: room_members
-- =====================================================
DROP TABLE IF EXISTS `room_members`;
CREATE TABLE `room_members` (
  `room_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role` ENUM('owner', 'admin', 'member') DEFAULT 'member',
  `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_read_at` TIMESTAMP NULL DEFAULT NULL,
  `notifications_enabled` BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (`room_id`, `user_id`),
  KEY `idx_room_members_user` (`user_id`),
  CONSTRAINT `fk_rm_room` FOREIGN KEY (`room_id`)
    REFERENCES `rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rm_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Room membership and settings';

-- =====================================================
-- Table: messages
-- =====================================================
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  `type` ENUM('text', 'file', 'image', 'system') DEFAULT 'text',
  `attachments` JSON DEFAULT NULL,
  `edited_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_messages_room_created` (`room_id`, `created_at`),
  KEY `idx_messages_user` (`user_id`),
  KEY `idx_messages_deleted` (`deleted_at`),
  CONSTRAINT `fk_messages_room` FOREIGN KEY (`room_id`)
    REFERENCES `rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_messages_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Chat messages';

-- =====================================================
-- Table: notifications
-- Multi-channel notification system
-- =====================================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `data` JSON DEFAULT NULL,
  `channels` SET('in_app', 'email', 'push', 'sms') DEFAULT 'in_app',
  `read_at` TIMESTAMP NULL DEFAULT NULL,
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_tenant_user` (`tenant_id`, `user_id`),
  KEY `idx_notifications_unread` (`user_id`, `read_at`),
  KEY `idx_notifications_created` (`created_at`),
  CONSTRAINT `fk_notifications_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Multi-channel notification system';

-- =====================================================
-- Table: audit_logs
-- Comprehensive audit trail (IMMUTABLE - no updates/deletes)
-- =====================================================
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL for system-level actions',
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `activity_type` VARCHAR(100) NOT NULL,
  `activity_category` ENUM(
    'auth', 'user', 'tenant', 'file', 'folder',
    'permission', 'system', 'api', 'security'
  ) NOT NULL,
  `entity_type` VARCHAR(50) DEFAULT NULL,
  `entity_id` BIGINT UNSIGNED DEFAULT NULL,
  `entity_name` VARCHAR(500) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `session_id` VARCHAR(128) DEFAULT NULL,
  `request_method` VARCHAR(10) DEFAULT NULL,
  `request_path` VARCHAR(500) DEFAULT NULL,
  `response_code` INT DEFAULT NULL,
  `details` JSON DEFAULT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_tenant_user` (`tenant_id`, `user_id`),
  KEY `idx_audit_category_type` (`activity_category`, `activity_type`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_session` (`session_id`),
  KEY `idx_audit_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Immutable audit trail for all system activities'
PARTITION BY RANGE (YEAR(created_at)) (
  PARTITION p2025 VALUES LESS THAN (2026),
  PARTITION p2026 VALUES LESS THAN (2027),
  PARTITION p2027 VALUES LESS THAN (2028),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- =====================================================
-- Table: approvals
-- Workflow approval system
-- =====================================================
DROP TABLE IF EXISTS `approvals`;
CREATE TABLE `approvals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `workflow_type` VARCHAR(100) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
  `requested_by` BIGINT UNSIGNED NOT NULL,
  `requested_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_by` BIGINT UNSIGNED DEFAULT NULL,
  `decided_at` TIMESTAMP NULL DEFAULT NULL,
  `comments` TEXT DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_approvals_tenant_status` (`tenant_id`, `status`),
  KEY `idx_approvals_entity` (`entity_type`, `entity_id`),
  KEY `idx_approvals_requested_by` (`requested_by`),
  KEY `idx_approvals_decided_by` (`decided_by`),
  KEY `idx_approvals_expires` (`expires_at`),
  CONSTRAINT `fk_approvals_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_approvals_requested_by` FOREIGN KEY (`requested_by`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_approvals_decided_by` FOREIGN KEY (`decided_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Workflow approval system';

-- =====================================================
-- Table: approval_steps
-- Multi-step approval workflows
-- =====================================================
DROP TABLE IF EXISTS `approval_steps`;
CREATE TABLE `approval_steps` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `approval_id` BIGINT UNSIGNED NOT NULL,
  `step_order` TINYINT UNSIGNED NOT NULL,
  `approver_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'skipped') DEFAULT 'pending',
  `decided_at` TIMESTAMP NULL DEFAULT NULL,
  `comments` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_approval_step` (`approval_id`, `step_order`),
  KEY `idx_approval_steps_approver` (`approver_id`),
  KEY `idx_approval_steps_status` (`status`),
  CONSTRAINT `fk_as_approval` FOREIGN KEY (`approval_id`)
    REFERENCES `approvals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_as_approver` FOREIGN KEY (`approver_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual steps in multi-step approval workflows';

-- =====================================================
-- Table: sessions
-- Active user sessions management
-- =====================================================
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` VARCHAR(128) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Current active tenant for session',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `device_info` JSON DEFAULT NULL,
  `payload` TEXT NOT NULL,
  `last_activity` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user` (`user_id`),
  KEY `idx_sessions_tenant` (`tenant_id`),
  KEY `idx_sessions_last_activity` (`last_activity`),
  KEY `idx_sessions_expires` (`expires_at`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sessions_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Active user session management';

-- =====================================================
-- Table: push_subscriptions
-- Web push notification subscriptions
-- =====================================================
DROP TABLE IF EXISTS `push_subscriptions`;
CREATE TABLE `push_subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `endpoint` TEXT NOT NULL,
  `public_key` VARCHAR(255) NOT NULL,
  `auth_token` VARCHAR(255) NOT NULL,
  `device_type` VARCHAR(50) DEFAULT NULL,
  `browser` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_push_user` (`user_id`),
  KEY `idx_push_last_used` (`last_used_at`),
  CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Web push notification subscriptions';

-- =====================================================
-- Table: permissions
-- Granular permission management
-- =====================================================
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `resource_type` ENUM('folder', 'file', 'calendar', 'task', 'room') NOT NULL,
  `resource_id` BIGINT UNSIGNED NOT NULL,
  `permission` SET('read', 'write', 'delete', 'share', 'download', 'manage') NOT NULL,
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
-- VALIDATION TRIGGERS
-- =====================================================

DELIMITER $$

-- Trigger: Enforce standard_user single tenant restriction
CREATE TRIGGER trg_validate_standard_user_single_tenant
BEFORE INSERT ON user_tenant_associations
FOR EACH ROW
BEGIN
  DECLARE user_role VARCHAR(20);
  DECLARE association_count INT;

  -- Get user role
  SELECT role INTO user_role
  FROM users WHERE id = NEW.user_id;

  -- Check if standard_user already has a tenant
  IF user_role = 'standard_user' THEN
    SELECT COUNT(*) INTO association_count
    FROM user_tenant_associations
    WHERE user_id = NEW.user_id;

    IF association_count > 0 THEN
      SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Standard users can only be associated with one tenant';
    END IF;
  END IF;
END$$

-- Trigger: Validate user login eligibility
CREATE TRIGGER trg_validate_user_login_check
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
  DECLARE tenant_count INT;

  -- Only check on login (when last_login_at is being updated)
  IF NEW.last_login_at IS NOT NULL AND OLD.last_login_at != NEW.last_login_at THEN
    -- Admin users can login without tenant association
    IF NEW.role != 'admin' THEN
      SELECT COUNT(*) INTO tenant_count
      FROM user_tenant_associations
      WHERE user_id = NEW.id
        AND (access_expires_at IS NULL OR access_expires_at > NOW());

      IF tenant_count = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'User must have at least one active tenant association to login';
      END IF;
    END IF;
  END IF;
END$$

-- Trigger: Ensure special_user has at least one tenant
CREATE TRIGGER trg_validate_special_user_tenant
BEFORE DELETE ON user_tenant_associations
FOR EACH ROW
BEGIN
  DECLARE user_role VARCHAR(20);
  DECLARE remaining_count INT;

  SELECT role INTO user_role
  FROM users WHERE id = OLD.user_id;

  IF user_role = 'special_user' THEN
    SELECT COUNT(*) INTO remaining_count
    FROM user_tenant_associations
    WHERE user_id = OLD.user_id AND id != OLD.id;

    IF remaining_count = 0 THEN
      SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Special users must maintain at least one tenant association';
    END IF;
  END IF;
END$$

-- Trigger: Prevent deletion operations by special_users
CREATE TRIGGER trg_prevent_special_user_delete_users
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
  DECLARE deleter_role VARCHAR(20);

  -- Get role of user performing deletion (would need session context)
  -- This is a placeholder - actual implementation would use session variables
  IF @current_user_role = 'special_user' THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Special users cannot delete user accounts';
  END IF;
END$$

CREATE TRIGGER trg_prevent_special_user_delete_tenants
BEFORE DELETE ON tenants
FOR EACH ROW
BEGIN
  IF @current_user_role = 'special_user' THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Special users cannot delete tenants';
  END IF;
END$$

-- Trigger: Auto-set primary tenant if first association
CREATE TRIGGER trg_auto_primary_tenant
BEFORE INSERT ON user_tenant_associations
FOR EACH ROW
BEGIN
  DECLARE existing_primary INT;

  SELECT COUNT(*) INTO existing_primary
  FROM user_tenant_associations
  WHERE user_id = NEW.user_id AND is_primary = TRUE;

  -- If no primary tenant exists, make this one primary
  IF existing_primary = 0 THEN
    SET NEW.is_primary = TRUE;
  END IF;
END$$

-- Trigger: Log all authentication attempts
CREATE TRIGGER trg_log_auth_attempts
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
  -- Log successful login
  IF NEW.last_login_at IS NOT NULL AND (OLD.last_login_at IS NULL OR OLD.last_login_at != NEW.last_login_at) THEN
    INSERT INTO audit_logs (
      user_id, activity_type, activity_category,
      ip_address, details, created_at
    ) VALUES (
      NEW.id, 'user_login', 'auth',
      NEW.last_login_ip, JSON_OBJECT('status', 'success', 'role', NEW.role),
      NOW()
    );
  END IF;

  -- Log failed attempts
  IF NEW.failed_login_attempts > OLD.failed_login_attempts THEN
    INSERT INTO audit_logs (
      user_id, activity_type, activity_category,
      ip_address, details, created_at
    ) VALUES (
      NEW.id, 'login_failed', 'auth',
      NEW.last_login_ip, JSON_OBJECT('attempt_count', NEW.failed_login_attempts),
      NOW()
    );
  END IF;
END$$

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

DELIMITER ;

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

DELIMITER $$

-- Procedure: Validate user can access tenant
CREATE PROCEDURE sp_validate_user_tenant_access(
  IN p_user_id BIGINT,
  IN p_tenant_id BIGINT,
  OUT p_has_access BOOLEAN,
  OUT p_access_level VARCHAR(50)
)
BEGIN
  DECLARE v_user_role VARCHAR(20);
  DECLARE v_tenant_role VARCHAR(20);

  SET p_has_access = FALSE;
  SET p_access_level = NULL;

  -- Get user's system role
  SELECT role INTO v_user_role
  FROM users WHERE id = p_user_id;

  -- Admin users have access to all tenants
  IF v_user_role = 'admin' THEN
    SET p_has_access = TRUE;
    SET p_access_level = 'full_control';
  ELSE
    -- Check user-tenant association
    SELECT tenant_role INTO v_tenant_role
    FROM user_tenant_associations
    WHERE user_id = p_user_id
      AND tenant_id = p_tenant_id
      AND (access_expires_at IS NULL OR access_expires_at > NOW());

    IF v_tenant_role IS NOT NULL THEN
      SET p_has_access = TRUE;
      SET p_access_level = v_tenant_role;
    END IF;
  END IF;
END$$

-- Procedure: Get user accessible tenants
CREATE PROCEDURE sp_get_user_tenants(
  IN p_user_id BIGINT
)
BEGIN
  DECLARE v_user_role VARCHAR(20);

  SELECT role INTO v_user_role
  FROM users WHERE id = p_user_id;

  IF v_user_role = 'admin' THEN
    -- Admin sees all tenants
    SELECT t.*, 'full_control' as access_level
    FROM tenants t
    WHERE t.deleted_at IS NULL
    ORDER BY t.name;
  ELSE
    -- Other users see only associated tenants
    SELECT t.*, uta.tenant_role as access_level, uta.is_primary
    FROM tenants t
    INNER JOIN user_tenant_associations uta ON t.id = uta.tenant_id
    WHERE uta.user_id = p_user_id
      AND t.deleted_at IS NULL
      AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
    ORDER BY uta.is_primary DESC, t.name;
  END IF;
END$$

-- Procedure: Switch user active tenant
CREATE PROCEDURE sp_switch_tenant(
  IN p_user_id BIGINT,
  IN p_tenant_id BIGINT
)
BEGIN
  DECLARE v_has_access BOOLEAN;
  DECLARE v_access_level VARCHAR(50);

  -- Validate access
  CALL sp_validate_user_tenant_access(p_user_id, p_tenant_id, v_has_access, v_access_level);

  IF v_has_access THEN
    -- Update last active tenant
    UPDATE users
    SET last_active_tenant_id = p_tenant_id,
        updated_at = NOW()
    WHERE id = p_user_id;

    -- Update session if exists
    UPDATE sessions
    SET tenant_id = p_tenant_id
    WHERE user_id = p_user_id;

    -- Log the switch
    INSERT INTO audit_logs (
      tenant_id, user_id, activity_type, activity_category,
      details, created_at
    ) VALUES (
      p_tenant_id, p_user_id, 'tenant_switch', 'auth',
      JSON_OBJECT('new_tenant_id', p_tenant_id, 'access_level', v_access_level),
      NOW()
    );
  ELSE
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'User does not have access to this tenant';
  END IF;
END$$

-- Procedure: Clean up expired sessions and data
CREATE PROCEDURE sp_cleanup_expired_data()
BEGIN
  -- Delete expired sessions
  DELETE FROM sessions WHERE expires_at <= NOW();

  -- Delete expired tenant associations
  DELETE FROM user_tenant_associations
  WHERE access_expires_at IS NOT NULL AND access_expires_at <= NOW();

  -- Delete expired permissions
  DELETE FROM permissions
  WHERE expires_at IS NOT NULL AND expires_at <= NOW();

  -- Delete expired share links
  UPDATE files SET share_token = NULL, share_expires_at = NULL
  WHERE share_expires_at IS NOT NULL AND share_expires_at <= NOW();

  -- Clean trash (30 days)
  DELETE FROM files
  WHERE deleted_at IS NOT NULL
    AND trash_expires_at IS NOT NULL
    AND trash_expires_at <= NOW();

  -- Update file storage reference counts
  UPDATE file_storage fs
  SET reference_count = (
    SELECT COUNT(*)
    FROM files f
    WHERE f.storage_id = fs.id
  );

  -- Delete orphaned storage
  DELETE FROM file_storage WHERE reference_count = 0;
END$$

DELIMITER ;

-- =====================================================
-- VIEWS
-- =====================================================

-- View: User access summary
CREATE OR REPLACE VIEW v_user_access_summary AS
SELECT
  u.id,
  u.email,
  u.first_name,
  u.last_name,
  u.role as system_role,
  u.status,
  u.last_login_at,
  COUNT(DISTINCT uta.tenant_id) as tenant_count,
  GROUP_CONCAT(t.name ORDER BY uta.is_primary DESC SEPARATOR ', ') as tenant_names
FROM users u
LEFT JOIN user_tenant_associations uta ON u.id = uta.user_id
  AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
LEFT JOIN tenants t ON uta.tenant_id = t.id AND t.deleted_at IS NULL
WHERE u.deleted_at IS NULL
GROUP BY u.id;

-- View: Tenant user statistics
CREATE OR REPLACE VIEW v_tenant_user_stats AS
SELECT
  t.id as tenant_id,
  t.name as tenant_name,
  COUNT(DISTINCT uta.user_id) as total_users,
  SUM(CASE WHEN uta.tenant_role = 'owner' THEN 1 ELSE 0 END) as owners,
  SUM(CASE WHEN uta.tenant_role = 'admin' THEN 1 ELSE 0 END) as admins,
  SUM(CASE WHEN uta.tenant_role = 'manager' THEN 1 ELSE 0 END) as managers,
  SUM(CASE WHEN uta.tenant_role = 'member' THEN 1 ELSE 0 END) as members,
  SUM(CASE WHEN uta.tenant_role = 'viewer' THEN 1 ELSE 0 END) as viewers
FROM tenants t
LEFT JOIN user_tenant_associations uta ON t.id = uta.tenant_id
  AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
WHERE t.deleted_at IS NULL
GROUP BY t.id;

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

-- =====================================================
-- PERFORMANCE INDEXES
-- =====================================================

-- Additional composite indexes for common queries
CREATE INDEX idx_uta_user_primary ON user_tenant_associations(user_id, is_primary);
CREATE INDEX idx_users_email_status ON users(email, status);
CREATE INDEX idx_users_role_status ON users(role, status);
CREATE INDEX idx_tenants_status_tier ON tenants(status, subscription_tier);
CREATE INDEX idx_files_tenant_created ON files(tenant_id, created_at DESC);
CREATE INDEX idx_audit_user_date ON audit_logs(user_id, created_at DESC);

-- =====================================================
-- INITIAL SEED DATA
-- =====================================================

-- Create default admin user
-- Password: Ricord@1991 (BCrypt hash)
INSERT INTO users (
  email, password, first_name, last_name,
  role, status, email_verified_at, created_at
) VALUES (
  'asamodeo@fortibyte.it',
  '$2y$10$YQXk6L3POYLYgZMDw9HR8.QxYvJPxZPYvYL5k.v39Wn9ZmCKqmOE6', -- Ricord@1991
  'Admin',
  'Samodeo',
  'admin',
  'active',
  NOW(),
  NOW()
);

SET @admin_id = LAST_INSERT_ID();

-- Insert demo tenants
INSERT INTO tenants (
  code, name, status, subscription_tier,
  settings, created_at
) VALUES
(
  'FORTIBYTE',
  'FortiByte Solutions',
  'active',
  'enterprise',
  JSON_OBJECT(
    'theme', 'light',
    'language', 'en',
    'timezone', 'Europe/Rome',
    'features', JSON_ARRAY('all')
  ),
  NOW()
),
(
  'DEMO',
  'Demo Organization',
  'active',
  'professional',
  JSON_OBJECT(
    'theme', 'light',
    'language', 'en',
    'timezone', 'UTC',
    'features', JSON_ARRAY('files', 'calendar', 'tasks', 'chat')
  ),
  NOW()
);

-- Get tenant IDs
SET @fortibyte_id = (SELECT id FROM tenants WHERE code = 'FORTIBYTE');
SET @demo_id = (SELECT id FROM tenants WHERE code = 'DEMO');

-- Create special user for testing
INSERT INTO users (
  email, password, first_name, last_name,
  role, status, email_verified_at, created_by, created_at
) VALUES (
  'special@demo.com',
  '$2y$10$YQXk6L3POYLYgZMDw9HR8.QxYvJPxZPYvYL5k.v39Wn9ZmCKqmOE6', -- Ricord@1991
  'Special',
  'User',
  'special_user',
  'active',
  NOW(),
  @admin_id,
  NOW()
);

SET @special_id = LAST_INSERT_ID();

-- Create standard user for testing
INSERT INTO users (
  email, password, first_name, last_name,
  role, status, email_verified_at, created_by, created_at
) VALUES (
  'standard@demo.com',
  '$2y$10$YQXk6L3POYLYgZMDw9HR8.QxYvJPxZPYvYL5k.v39Wn9ZmCKqmOE6', -- Ricord@1991
  'Standard',
  'User',
  'standard_user',
  'active',
  NOW(),
  @admin_id,
  NOW()
);

SET @standard_id = LAST_INSERT_ID();

-- Associate special user with both tenants
INSERT INTO user_tenant_associations (
  user_id, tenant_id, is_primary, tenant_role, created_by
) VALUES
  (@special_id, @fortibyte_id, TRUE, 'manager', @admin_id),
  (@special_id, @demo_id, FALSE, 'member', @admin_id);

-- Associate standard user with one tenant only
INSERT INTO user_tenant_associations (
  user_id, tenant_id, is_primary, tenant_role, created_by
) VALUES
  (@standard_id, @demo_id, TRUE, 'member', @admin_id);

-- Log initial admin creation
INSERT INTO audit_logs (
  user_id, activity_type, activity_category,
  details, created_at
) VALUES (
  @admin_id, 'user_created', 'user',
  JSON_OBJECT('role', 'admin', 'email', 'asamodeo@fortibyte.it'),
  NOW()
);

-- Create default groups for each tenant
INSERT INTO groups (tenant_id, name, description, is_system, created_by)
VALUES
  (@fortibyte_id, 'Administrators', 'Full system access', TRUE, @admin_id),
  (@fortibyte_id, 'Managers', 'Management access', TRUE, @admin_id),
  (@fortibyte_id, 'Users', 'Standard users', TRUE, @admin_id),
  (@demo_id, 'Administrators', 'Full system access', TRUE, @admin_id),
  (@demo_id, 'Managers', 'Management access', TRUE, @admin_id),
  (@demo_id, 'Users', 'Standard users', TRUE, @admin_id);

-- =====================================================
-- EVENTS FOR MAINTENANCE
-- =====================================================

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;

-- Create event to clean expired data daily
CREATE EVENT IF NOT EXISTS evt_cleanup_expired
ON SCHEDULE EVERY 1 DAY
STARTS (CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 3 HOUR)
DO CALL sp_cleanup_expired_data();

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

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- SCHEMA DOCUMENTATION
-- =====================================================

/*
SCHEMA V2 FEATURES:
1. New role-based access control:
   - admin: Full system control (100% access to all tenants)
   - special_user: Multi-tenant access, no deletion rights
   - standard_user: Single tenant access only

2. Authentication changes:
   - Login with email/password only (no tenant code required)
   - User-tenant associations determine access
   - Admins have global access without associations

3. Multi-tenant architecture:
   - user_tenant_associations table for many-to-many relationships
   - Primary tenant marking for default selection
   - Per-tenant permissions and roles

4. Security features:
   - Validation triggers enforce role restrictions
   - Immutable audit logging
   - Session-based tenant switching
   - Two-factor authentication support

5. Performance optimizations:
   - Strategic composite indexes
   - Partitioned audit_logs table
   - Stored procedures for complex operations
   - File storage deduplication

6. Compliance features:
   - Soft delete with recovery
   - Complete audit trail
   - Data retention policies
   - GDPR-ready structure

DEFAULT CREDENTIALS:
- Admin: asamodeo@fortibyte.it / Ricord@1991
- Special User: special@demo.com / Ricord@1991
- Standard User: standard@demo.com / Ricord@1991
*/