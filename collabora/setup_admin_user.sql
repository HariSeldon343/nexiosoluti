-- =============================================================
-- Setup Admin User for Nexio Collabora System
-- Database: nexio_collabora_v2 (or collabora_files as fallback)
-- Admin user: asamodeo@fortibyte.it / Ricord@1991
-- =============================================================

-- Try both possible database names
CREATE DATABASE IF NOT EXISTS nexio_collabora_v2
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS collabora_files
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Use the primary database
USE nexio_collabora_v2;

-- =============================================================
-- Create Tables (Idempotent - IF NOT EXISTS)
-- =============================================================

-- Table: Tenants
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255),
    settings JSON,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    storage_limit BIGINT DEFAULT 10737418240, -- 10GB default
    storage_used BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    username VARCHAR(100),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    full_name VARCHAR(255),
    role ENUM('admin', 'special_user', 'standard_user', 'manager', 'user') DEFAULT 'standard_user',
    status ENUM('active', 'inactive', 'locked', 'blocked') DEFAULT 'active',
    is_active BOOLEAN DEFAULT TRUE,
    phone VARCHAR(50),
    timezone VARCHAR(50) DEFAULT 'Europe/Rome',
    language VARCHAR(10) DEFAULT 'it',
    avatar VARCHAR(255),
    is_system_admin BOOLEAN DEFAULT FALSE,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL DEFAULT NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    last_activity TIMESTAMP NULL DEFAULT NULL,
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: User-Tenant Associations (supports multi-tenancy)
CREATE TABLE IF NOT EXISTS user_tenant_associations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    permissions JSON,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_tenant (user_id, tenant_id),
    INDEX idx_user_tenant (user_id, tenant_id),
    INDEX idx_default (user_id, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alternative table name (some code might use this)
CREATE TABLE IF NOT EXISTS user_tenants LIKE user_tenant_associations;

-- Table: Sessions
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    INDEX idx_user_sessions (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: Login Attempts (security tracking)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255),
    ip_address VARCHAR(45),
    success BOOLEAN DEFAULT FALSE,
    error_message TEXT,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_ip (email, ip_address),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: Folders (for file management)
CREATE TABLE IF NOT EXISTS folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    path TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_parent (parent_id),
    INDEX idx_created_by (created_by),
    CONSTRAINT fk_folders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_folders_parent FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE,
    CONSTRAINT fk_folders_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: Files
CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    folder_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100),
    extension VARCHAR(20),
    size BIGINT NOT NULL,
    hash VARCHAR(64),
    path TEXT NOT NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP NULL,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_folder (folder_id),
    INDEX idx_hash (hash),
    INDEX idx_deleted (is_deleted, deleted_at),
    INDEX idx_uploaded_by (uploaded_by),
    CONSTRAINT fk_files_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_files_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL,
    CONSTRAINT fk_files_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Insert/Update Default Tenant
-- =============================================================

INSERT INTO tenants (code, name, settings, status) VALUES
('DEFAULT', 'Default Tenant', JSON_OBJECT(
    'allow_signup', false,
    'max_users', 100,
    'features', JSON_ARRAY('files', 'calendar', 'tasks', 'chat')
), 'active')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = 'active',
    updated_at = CURRENT_TIMESTAMP;

-- Also create a FORTIBYTE tenant for the admin
INSERT INTO tenants (code, name, domain, settings, status) VALUES
('FORTIBYTE', 'Fortibyte Solutions', 'fortibyte.it', JSON_OBJECT(
    'allow_signup', false,
    'max_users', 500,
    'features', JSON_ARRAY('files', 'calendar', 'tasks', 'chat', 'admin')
), 'active')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = 'active',
    updated_at = CURRENT_TIMESTAMP;

-- =============================================================
-- Insert/Update Admin User
-- Password: Ricord@1991
-- Using PHP's password_hash() with BCRYPT
-- Hash: $2y$10$Fp7.kCQzUb3nB8zWxRHU7OA0xHRlL1GzXzKIh0V8S/hqKCfH0xMLO
-- =============================================================

-- First, delete any existing admin user to avoid conflicts
DELETE FROM users WHERE email = 'asamodeo@fortibyte.it';

-- Insert admin user with proper password hash
INSERT INTO users (
    email,
    password,
    username,
    first_name,
    last_name,
    full_name,
    role,
    status,
    is_active,
    is_system_admin,
    settings
) VALUES (
    'asamodeo@fortibyte.it',
    '$2y$10$Fp7.kCQzUb3nB8zWxRHU7OA0xHRlL1GzXzKIh0V8S/hqKCfH0xMLO', -- Ricord@1991
    'asamodeo',
    'Admin',
    'Samodeo',
    'Admin Samodeo',
    'admin',
    'active',
    TRUE,
    TRUE,
    JSON_OBJECT(
        'theme', 'light',
        'notifications', true,
        'dashboard_widgets', JSON_ARRAY('stats', 'recent_files', 'activities')
    )
);

-- Get the admin user ID
SET @admin_id = LAST_INSERT_ID();

-- =============================================================
-- Associate Admin with Tenants
-- =============================================================

-- Link admin to DEFAULT tenant
INSERT INTO user_tenant_associations (user_id, tenant_id, role, is_default, permissions)
SELECT
    @admin_id,
    t.id,
    'admin',
    TRUE,
    JSON_OBJECT(
        'files', JSON_ARRAY('create', 'read', 'update', 'delete'),
        'users', JSON_ARRAY('create', 'read', 'update', 'delete'),
        'tenants', JSON_ARRAY('create', 'read', 'update', 'delete'),
        'settings', JSON_ARRAY('manage')
    )
FROM tenants t
WHERE t.code = 'DEFAULT'
ON DUPLICATE KEY UPDATE
    role = VALUES(role),
    is_default = VALUES(is_default),
    permissions = VALUES(permissions),
    updated_at = CURRENT_TIMESTAMP;

-- Link admin to FORTIBYTE tenant
INSERT INTO user_tenant_associations (user_id, tenant_id, role, is_default, permissions)
SELECT
    @admin_id,
    t.id,
    'admin',
    FALSE,
    JSON_OBJECT(
        'files', JSON_ARRAY('create', 'read', 'update', 'delete'),
        'users', JSON_ARRAY('create', 'read', 'update', 'delete'),
        'tenants', JSON_ARRAY('create', 'read', 'update', 'delete'),
        'settings', JSON_ARRAY('manage')
    )
FROM tenants t
WHERE t.code = 'FORTIBYTE'
ON DUPLICATE KEY UPDATE
    role = VALUES(role),
    permissions = VALUES(permissions),
    updated_at = CURRENT_TIMESTAMP;

-- Also insert into user_tenants table (if it exists as separate table)
INSERT IGNORE INTO user_tenants (user_id, tenant_id, role, is_default)
SELECT user_id, tenant_id, role, is_default
FROM user_tenant_associations
WHERE user_id = @admin_id;

-- =============================================================
-- Create Additional Test Users (Optional)
-- =============================================================

-- Standard user for testing
INSERT IGNORE INTO users (
    email,
    password,
    first_name,
    last_name,
    role,
    status,
    is_active
) VALUES (
    'user@example.com',
    '$2y$10$Fp7.kCQzUb3nB8zWxRHU7OA0xHRlL1GzXzKIh0V8S/hqKCfH0xMLO', -- Ricord@1991
    'Test',
    'User',
    'standard_user',
    'active',
    TRUE
);

-- Associate test user with DEFAULT tenant
INSERT IGNORE INTO user_tenant_associations (user_id, tenant_id, role, is_default)
SELECT u.id, t.id, 'user', TRUE
FROM users u, tenants t
WHERE u.email = 'user@example.com'
AND t.code = 'DEFAULT';

-- =============================================================
-- Grant Privileges for Local Access
-- =============================================================

GRANT ALL PRIVILEGES ON nexio_collabora_v2.* TO 'root'@'localhost';
GRANT ALL PRIVILEGES ON collabora_files.* TO 'root'@'localhost';
FLUSH PRIVILEGES;

-- =============================================================
-- Display Results
-- =============================================================

SELECT '=== Admin User Created/Updated ===' as Status;
SELECT id, email, role, is_system_admin, is_active, status
FROM users
WHERE email = 'asamodeo@fortibyte.it';

SELECT '=== Tenants Created ===' as Status;
SELECT id, code, name, status
FROM tenants;

SELECT '=== User-Tenant Associations ===' as Status;
SELECT
    u.email,
    t.code as tenant_code,
    uta.role as tenant_role,
    uta.is_default
FROM user_tenant_associations uta
JOIN users u ON u.id = uta.user_id
JOIN tenants t ON t.id = uta.tenant_id
WHERE u.email = 'asamodeo@fortibyte.it';