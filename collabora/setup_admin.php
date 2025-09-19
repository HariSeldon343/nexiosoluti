<?php
/**
 * Setup Admin User Script
 * Creates/updates the admin user with proper password hash
 *
 * Admin credentials:
 * Email: asamodeo@fortibyte.it
 * Password: Ricord@1991
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Output header
header('Content-Type: text/plain; charset=utf-8');
echo "=============================================================\n";
echo "Nexio Collabora - Admin User Setup Script\n";
echo "=============================================================\n\n";

// Database configuration
$configs = [
    // Primary configuration
    [
        'host' => 'localhost',
        'name' => 'nexio_collabora_v2',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],
    // Alternative configuration
    [
        'host' => 'localhost',
        'name' => 'collabora_files',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ]
];

$pdo = null;
$dbName = null;

// Try to connect to database
foreach ($configs as $config) {
    echo "Attempting to connect to database: {$config['name']}...\n";

    try {
        // First, try to create the database if it doesn't exist
        $tempPdo = new PDO(
            "mysql:host={$config['host']};charset={$config['charset']}",
            $config['user'],
            $config['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['name']}`
                        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Now connect to the specific database
        $dsn = "mysql:host={$config['host']};dbname={$config['name']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        $dbName = $config['name'];
        echo "✓ Successfully connected to database: {$config['name']}\n\n";
        break;

    } catch (PDOException $e) {
        echo "✗ Failed to connect: " . $e->getMessage() . "\n\n";
    }
}

if (!$pdo) {
    die("ERROR: Could not connect to any database. Please check your MySQL server.\n");
}

// Function to execute SQL and report results
function executeSql($pdo, $sql, $description) {
    echo "$description...\n";
    try {
        $pdo->exec($sql);
        echo "✓ Success\n";
        return true;
    } catch (PDOException $e) {
        echo "✗ Failed: " . $e->getMessage() . "\n";
        return false;
    }
}

// Function to check if table exists
function tableExists($pdo, $tableName) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tableName]);
    return $stmt->rowCount() > 0;
}

echo "=============================================================\n";
echo "Step 1: Creating Tables\n";
echo "=============================================================\n\n";

// Create tenants table
$sql = "CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255),
    settings JSON,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    storage_limit BIGINT DEFAULT 10737418240,
    storage_used BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
executeSql($pdo, $sql, "Creating tenants table");

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    role ENUM('admin', 'special_user', 'standard_user') DEFAULT 'standard_user',
    status ENUM('active', 'inactive', 'locked') DEFAULT 'active',
    phone VARCHAR(50),
    timezone VARCHAR(50) DEFAULT 'Europe/Rome',
    language VARCHAR(10) DEFAULT 'it',
    avatar VARCHAR(255),
    is_system_admin BOOLEAN DEFAULT FALSE,
    tenant_id INT DEFAULT NULL,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL DEFAULT NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    last_activity TIMESTAMP NULL DEFAULT NULL,
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
executeSql($pdo, $sql, "Creating users table");

// Create user_tenant_associations table
$sql = "CREATE TABLE IF NOT EXISTS user_tenant_associations (
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
    INDEX idx_user_tenant (user_id, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
executeSql($pdo, $sql, "Creating user_tenant_associations table");

// Create alternative user_tenants table (some code might use this name)
$sql = "CREATE TABLE IF NOT EXISTS user_tenants LIKE user_tenant_associations";
executeSql($pdo, $sql, "Creating user_tenants table (alias)");

echo "\n=============================================================\n";
echo "Step 2: Creating Default Tenant\n";
echo "=============================================================\n\n";

// Check if DEFAULT tenant exists
$stmt = $pdo->prepare("SELECT id FROM tenants WHERE code = 'DEFAULT'");
$stmt->execute();
$defaultTenant = $stmt->fetch();

if (!$defaultTenant) {
    $sql = "INSERT INTO tenants (code, name, settings, status) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $settings = json_encode([
        'allow_signup' => false,
        'max_users' => 100,
        'features' => ['files', 'calendar', 'tasks', 'chat']
    ]);
    $stmt->execute(['DEFAULT', 'Default Tenant', $settings, 'active']);
    $defaultTenantId = $pdo->lastInsertId();
    echo "✓ Created DEFAULT tenant (ID: $defaultTenantId)\n";
} else {
    $defaultTenantId = $defaultTenant['id'];
    echo "✓ DEFAULT tenant already exists (ID: $defaultTenantId)\n";
}

// Create FORTIBYTE tenant
$stmt = $pdo->prepare("SELECT id FROM tenants WHERE code = 'FORTIBYTE'");
$stmt->execute();
$fortibyteTenant = $stmt->fetch();

if (!$fortibyteTenant) {
    $sql = "INSERT INTO tenants (code, name, domain, settings, status) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $settings = json_encode([
        'allow_signup' => false,
        'max_users' => 500,
        'features' => ['files', 'calendar', 'tasks', 'chat', 'admin']
    ]);
    $stmt->execute(['FORTIBYTE', 'Fortibyte Solutions', 'fortibyte.it', $settings, 'active']);
    $fortibyteId = $pdo->lastInsertId();
    echo "✓ Created FORTIBYTE tenant (ID: $fortibyteId)\n";
} else {
    $fortibyteId = $fortibyteTenant['id'];
    echo "✓ FORTIBYTE tenant already exists (ID: $fortibyteId)\n";
}

echo "\n=============================================================\n";
echo "Step 3: Creating/Updating Admin User\n";
echo "=============================================================\n\n";

$adminEmail = 'asamodeo@fortibyte.it';
$adminPassword = 'Ricord@1991';

// Generate password hash using bcrypt
$passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);
echo "Generated password hash: $passwordHash\n\n";

// Check if admin user exists
$stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
$stmt->execute([$adminEmail]);
$existingAdmin = $stmt->fetch();

if ($existingAdmin) {
    // Update existing admin user
    echo "Admin user exists, updating...\n";

    $sql = "UPDATE users SET
            password = ?,
            first_name = ?,
            last_name = ?,
            role = ?,
            status = ?,
            is_system_admin = ?,
            failed_login_attempts = 0,
            locked_until = NULL,
            settings = ?
            WHERE email = ?";

    $settings = json_encode([
        'theme' => 'light',
        'notifications' => true,
        'dashboard_widgets' => ['stats', 'recent_files', 'activities']
    ]);

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $passwordHash,
        'Admin',
        'Samodeo',
        'admin',
        'active',
        1,
        $settings,
        $adminEmail
    ]);

    $adminId = $existingAdmin['id'];
    echo "✓ Admin user updated (ID: $adminId)\n";

} else {
    // Create new admin user
    echo "Creating new admin user...\n";

    $sql = "INSERT INTO users (
            email, password, first_name, last_name,
            role, status, is_system_admin, settings
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $settings = json_encode([
        'theme' => 'light',
        'notifications' => true,
        'dashboard_widgets' => ['stats', 'recent_files', 'activities']
    ]);

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $adminEmail,
        $passwordHash,
        'Admin',
        'Samodeo',
        'admin',
        'active',
        1,
        $settings
    ]);

    $adminId = $pdo->lastInsertId();
    echo "✓ Admin user created (ID: $adminId)\n";
}

echo "\n=============================================================\n";
echo "Step 4: Associating Admin with Tenants\n";
echo "=============================================================\n\n";

// Associate with DEFAULT tenant
$stmt = $pdo->prepare("SELECT id FROM user_tenant_associations WHERE user_id = ? AND tenant_id = ?");
$stmt->execute([$adminId, $defaultTenantId]);

if (!$stmt->fetch()) {
    $sql = "INSERT INTO user_tenant_associations (user_id, tenant_id, role, is_default, permissions)
            VALUES (?, ?, ?, ?, ?)";

    $permissions = json_encode([
        'files' => ['create', 'read', 'update', 'delete'],
        'users' => ['create', 'read', 'update', 'delete'],
        'tenants' => ['create', 'read', 'update', 'delete'],
        'settings' => ['manage']
    ]);

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$adminId, $defaultTenantId, 'admin', 1, $permissions]);
    echo "✓ Admin associated with DEFAULT tenant\n";
} else {
    echo "✓ Admin already associated with DEFAULT tenant\n";
}

// Associate with FORTIBYTE tenant
$stmt = $pdo->prepare("SELECT id FROM user_tenant_associations WHERE user_id = ? AND tenant_id = ?");
$stmt->execute([$adminId, $fortibyteId]);

if (!$stmt->fetch()) {
    $sql = "INSERT INTO user_tenant_associations (user_id, tenant_id, role, is_default, permissions)
            VALUES (?, ?, ?, ?, ?)";

    $permissions = json_encode([
        'files' => ['create', 'read', 'update', 'delete'],
        'users' => ['create', 'read', 'update', 'delete'],
        'tenants' => ['create', 'read', 'update', 'delete'],
        'settings' => ['manage']
    ]);

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$adminId, $fortibyteId, 'admin', 0, $permissions]);
    echo "✓ Admin associated with FORTIBYTE tenant\n";
} else {
    echo "✓ Admin already associated with FORTIBYTE tenant\n";
}

echo "\n=============================================================\n";
echo "Step 5: Testing Admin Login\n";
echo "=============================================================\n\n";

// Retrieve admin user for testing
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$adminEmail]);
$admin = $stmt->fetch();

if ($admin) {
    echo "Admin user details:\n";
    echo "  - ID: {$admin['id']}\n";
    echo "  - Email: {$admin['email']}\n";
    echo "  - Role: {$admin['role']}\n";
    echo "  - Status: {$admin['status']}\n";
    echo "  - Is Active: " . ($admin['is_active'] ? 'Yes' : 'No') . "\n";
    echo "  - Is System Admin: " . ($admin['is_system_admin'] ? 'Yes' : 'No') . "\n\n";

    // Test password verification
    echo "Testing password verification...\n";
    echo "  - Provided password: $adminPassword\n";
    echo "  - Stored hash: {$admin['password']}\n";

    if (password_verify($adminPassword, $admin['password'])) {
        echo "✓ PASSWORD VERIFICATION SUCCESSFUL!\n";
        echo "  The admin can now login with:\n";
        echo "  Email: $adminEmail\n";
        echo "  Password: $adminPassword\n";
    } else {
        echo "✗ PASSWORD VERIFICATION FAILED!\n";
        echo "  There might be an issue with the password hash.\n";

        // Try to rehash and update
        echo "\nAttempting to fix password hash...\n";
        $newHash = password_hash($adminPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$newHash, $adminEmail]);

        // Test again
        if (password_verify($adminPassword, $newHash)) {
            echo "✓ Password hash fixed! Login should work now.\n";
        } else {
            echo "✗ Unable to fix password hash. Please check PHP configuration.\n";
        }
    }

    // Show tenant associations
    echo "\n=============================================================\n";
    echo "Admin Tenant Associations:\n";
    echo "=============================================================\n\n";

    $sql = "SELECT t.code, t.name, uta.role, uta.is_default
            FROM user_tenant_associations uta
            JOIN tenants t ON t.id = uta.tenant_id
            WHERE uta.user_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin['id']]);
    $associations = $stmt->fetchAll();

    foreach ($associations as $assoc) {
        echo "  - Tenant: {$assoc['name']} ({$assoc['code']})\n";
        echo "    Role: {$assoc['role']}\n";
        echo "    Default: " . ($assoc['is_default'] ? 'Yes' : 'No') . "\n\n";
    }

} else {
    echo "✗ ERROR: Admin user not found in database!\n";
}

echo "=============================================================\n";
echo "Setup Complete!\n";
echo "=============================================================\n\n";
echo "Database: $dbName\n";
echo "Admin Email: $adminEmail\n";
echo "Admin Password: $adminPassword\n\n";
echo "You can now login at:\n";
echo "http://localhost/Nexiosolution/collabora/\n\n";

// Show all users in the system
echo "=============================================================\n";
echo "All Users in System:\n";
echo "=============================================================\n\n";

$stmt = $pdo->query("SELECT id, email, role, status, is_system_admin FROM users ORDER BY id");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    echo "ID: {$user['id']} | Email: {$user['email']} | Role: {$user['role']} | Status: {$user['status']} | System Admin: " . ($user['is_system_admin'] ? 'Yes' : 'No') . "\n";
}

echo "\n=============================================================\n";
echo "Script execution completed successfully!\n";
echo "=============================================================\n";