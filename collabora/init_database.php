<?php declare(strict_types=1);

/**
 * Database Initialization Script
 * Creates database, tables, and default admin user
 */

// Configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'collabora_files';

// Admin credentials
$adminEmail = 'asamodeo@fortibyte.it';
$adminPassword = 'Ricord@1991';
$adminFirstName = 'Andrea';
$adminLastName = 'Samodeo';

try {
    // Connect to MySQL without database selection
    $pdo = new PDO(
        "mysql:host={$dbHost};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "Connected to MySQL server.\n";

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '{$dbName}' checked/created.\n";

    // Use the database
    $pdo->exec("USE `{$dbName}`");

    // Create tenants table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tenants` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `code` VARCHAR(20) DEFAULT NULL,
        `name` VARCHAR(255) NOT NULL,
        `domain` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('active', 'suspended', 'archived') NOT NULL DEFAULT 'active',
        `settings` JSON DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_tenants_code` (`code`),
        KEY `idx_tenants_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table 'tenants' checked/created.\n";

    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `email` VARCHAR(255) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `first_name` VARCHAR(100) NOT NULL,
        `last_name` VARCHAR(100) NOT NULL,
        `role` ENUM('admin', 'special_user', 'standard_user') NOT NULL DEFAULT 'standard_user',
        `is_system_admin` BOOLEAN DEFAULT FALSE,
        `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
        `status` ENUM('active', 'inactive', 'locked') NOT NULL DEFAULT 'active',
        `last_login_at` TIMESTAMP NULL DEFAULT NULL,
        `last_login_ip` VARCHAR(45) DEFAULT NULL,
        `failed_login_attempts` TINYINT UNSIGNED DEFAULT 0,
        `locked_until` TIMESTAMP NULL DEFAULT NULL,
        `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
        `settings` JSON DEFAULT NULL,
        `timezone` VARCHAR(50) DEFAULT 'Europe/Rome',
        `language` VARCHAR(5) DEFAULT 'it',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_users_email` (`email`),
        KEY `idx_users_role` (`role`),
        KEY `idx_users_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table 'users' checked/created.\n";

    // Create user_tenant_associations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_tenant_associations` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` BIGINT UNSIGNED NOT NULL,
        `tenant_id` BIGINT UNSIGNED NOT NULL,
        `role_in_tenant` VARCHAR(50) DEFAULT 'user',
        `is_primary` BOOLEAN DEFAULT FALSE,
        `permissions` JSON DEFAULT NULL,
        `last_accessed_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_user_tenant` (`user_id`, `tenant_id`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_is_primary` (`is_primary`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table 'user_tenant_associations' checked/created.\n";

    // Create user_sessions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_sessions` (
        `id` VARCHAR(128) NOT NULL,
        `user_id` BIGINT UNSIGNED NOT NULL,
        `current_tenant_id` BIGINT UNSIGNED DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` TEXT,
        `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_last_activity` (`last_activity`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table 'user_sessions' checked/created.\n";

    // Create activity_logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` BIGINT UNSIGNED DEFAULT NULL,
        `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
        `action` VARCHAR(100) NOT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` TEXT,
        `session_id` VARCHAR(128) DEFAULT NULL,
        `metadata` JSON DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_tenant_id` (`tenant_id`),
        KEY `idx_action` (`action`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table 'activity_logs' checked/created.\n";

    // Create permission_sets table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `permission_sets` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(50) NOT NULL,
        `permissions` JSON DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table 'permission_sets' checked/created.\n";

    // Check if default tenant exists
    $stmt = $pdo->query("SELECT id FROM tenants WHERE name = 'Default Tenant' LIMIT 1");
    $tenant = $stmt->fetch();

    if (!$tenant) {
        // Create default tenant
        $stmt = $pdo->prepare("INSERT INTO tenants (name, status) VALUES (:name, :status)");
        $stmt->execute([
            'name' => 'Default Tenant',
            'status' => 'active'
        ]);
        $tenantId = $pdo->lastInsertId();
        echo "Default tenant created with ID: {$tenantId}\n";
    } else {
        $tenantId = $tenant['id'];
        echo "Default tenant already exists with ID: {$tenantId}\n";
    }

    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $adminEmail]);
    $user = $stmt->fetch();

    if (!$user) {
        // Create admin user
        $hashedPassword = password_hash($adminPassword, PASSWORD_ARGON2ID);

        $stmt = $pdo->prepare("INSERT INTO users
            (email, password, first_name, last_name, role, is_system_admin, tenant_id, status, email_verified_at)
            VALUES
            (:email, :password, :first_name, :last_name, 'admin', TRUE, :tenant_id, 'active', NOW())");

        $stmt->execute([
            'email' => $adminEmail,
            'password' => $hashedPassword,
            'first_name' => $adminFirstName,
            'last_name' => $adminLastName,
            'tenant_id' => $tenantId
        ]);

        $userId = $pdo->lastInsertId();
        echo "Admin user created with ID: {$userId}\n";
        echo "Email: {$adminEmail}\n";
        echo "Password: {$adminPassword}\n";

        // Create association
        $stmt = $pdo->prepare("INSERT INTO user_tenant_associations
            (user_id, tenant_id, role_in_tenant, is_primary)
            VALUES
            (:user_id, :tenant_id, 'admin', TRUE)");

        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);
        echo "Admin user associated with default tenant.\n";
    } else {
        echo "Admin user already exists with ID: {$user['id']}\n";

        // Update password if needed
        $stmt = $pdo->prepare("UPDATE users SET
            password = :password,
            role = 'admin',
            is_system_admin = TRUE,
            status = 'active'
            WHERE email = :email");

        $hashedPassword = password_hash($adminPassword, PASSWORD_ARGON2ID);
        $stmt->execute([
            'password' => $hashedPassword,
            'email' => $adminEmail
        ]);
        echo "Admin user password updated.\n";
    }

    // Insert default permission sets
    $permissionSets = [
        'admin' => ['*'],
        'special_user' => ['files.*', 'folders.*', 'users.view', 'tenants.switch'],
        'standard_user' => ['files.own', 'folders.own', 'profile.edit']
    ];

    foreach ($permissionSets as $role => $permissions) {
        $stmt = $pdo->prepare("INSERT INTO permission_sets (name, permissions)
            VALUES (:name, :permissions)
            ON DUPLICATE KEY UPDATE permissions = :permissions");

        $stmt->execute([
            'name' => $role,
            'permissions' => json_encode($permissions)
        ]);
    }
    echo "Permission sets initialized.\n";

    echo "\n=================================\n";
    echo "Database initialization complete!\n";
    echo "=================================\n";
    echo "You can now login with:\n";
    echo "Email: {$adminEmail}\n";
    echo "Password: {$adminPassword}\n";
    echo "=================================\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}