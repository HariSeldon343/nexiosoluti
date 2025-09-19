-- Fix missing columns in users table
USE nexio_collabora_v2;

-- Add missing columns to users table
ALTER TABLE users
ADD COLUMN IF NOT EXISTS tenant_id INT DEFAULT NULL AFTER is_system_admin,
ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL DEFAULT NULL AFTER tenant_id,
ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER email_verified_at,
ADD FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL;

-- Add index for deleted_at
ALTER TABLE users ADD INDEX idx_deleted (deleted_at);

-- Update the test user password with a simpler hash for testing
UPDATE users
SET password = '$2y$10$5qHwmP4ETjXHUhzRJ0dUFu8M0FBfTUE8cQ0o1WczPY3BLAXoN5F3a'
WHERE email = 'asamodeo@fortibyte.it';

-- Verify the user
SELECT id, email, password, role, is_system_admin, status FROM users WHERE email = 'asamodeo@fortibyte.it';