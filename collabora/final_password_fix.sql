-- Final password update for test user
USE nexio_collabora_v2;

-- This hash is for password: Ricord@1991
-- Generated using PHP's password_hash with PASSWORD_DEFAULT (bcrypt)
UPDATE users
SET password = '$2y$10$YJpVlg6EQH5RVq7Jqmk.a.0nUeNt9WqO9fJHgeVF6KwfTynRLpKGK'
WHERE email = 'asamodeo@fortibyte.it';

-- Verify the update
SELECT id, email, SUBSTRING(password, 1, 30) as password_preview, role, is_system_admin, status
FROM users
WHERE email = 'asamodeo@fortibyte.it';