<?php
/**
 * Test Database Connection and Verify Admin User
 *
 * This script:
 * 1. Tests database connection
 * 2. Shows all users (emails only, no passwords)
 * 3. Verifies table structure
 * 4. Tests password verification for admin user
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Output header
header('Content-Type: text/plain; charset=utf-8');
echo "=============================================================\n";
echo "Database Connection and User Verification Test\n";
echo "=============================================================\n\n";

// Database configurations to try
$configs = [
    [
        'host' => 'localhost',
        'name' => 'nexio_collabora_v2',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],
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

// Test PHP extensions
echo "=============================================================\n";
echo "Step 1: Checking PHP Extensions\n";
echo "=============================================================\n\n";

$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext extension loaded\n";
    } else {
        echo "✗ $ext extension NOT loaded\n";
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    echo "\n⚠ Warning: Missing extensions may cause issues: " . implode(', ', $missingExtensions) . "\n";
}

// Test database connections
echo "\n=============================================================\n";
echo "Step 2: Testing Database Connections\n";
echo "=============================================================\n\n";

foreach ($configs as $config) {
    echo "Attempting to connect to: {$config['name']}...\n";

    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['name']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        $dbName = $config['name'];
        echo "✓ Successfully connected to: {$config['name']}\n\n";
        break;

    } catch (PDOException $e) {
        echo "✗ Connection failed: " . $e->getMessage() . "\n\n";
    }
}

if (!$pdo) {
    die("ERROR: Could not connect to any database.\n" .
        "Please ensure MySQL is running and the databases exist.\n");
}

// Show database info
echo "=============================================================\n";
echo "Connected Database Information\n";
echo "=============================================================\n\n";
echo "Database Name: $dbName\n";
echo "Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
echo "Connection Status: Active\n";

// Check tables
echo "\n=============================================================\n";
echo "Step 3: Checking Database Tables\n";
echo "=============================================================\n\n";

$requiredTables = [
    'users' => [
        'email', 'password', 'role', 'status', 'is_active'
    ],
    'tenants' => [
        'code', 'name', 'status'
    ],
    'user_tenant_associations' => [
        'user_id', 'tenant_id', 'role', 'is_default'
    ]
];

foreach ($requiredTables as $table => $requiredColumns) {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");

    if ($stmt->rowCount() > 0) {
        echo "✓ Table '$table' exists\n";

        // Check columns
        $stmt = $pdo->prepare("SHOW COLUMNS FROM $table");
        $stmt->execute();
        $columns = $stmt->fetchAll();
        $columnNames = array_column($columns, 'Field');

        $missingColumns = array_diff($requiredColumns, $columnNames);
        if (empty($missingColumns)) {
            echo "  ✓ All required columns present\n";
        } else {
            echo "  ⚠ Missing columns: " . implode(', ', $missingColumns) . "\n";
        }

        // Count records
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "  Records: $count\n\n";
    } else {
        echo "✗ Table '$table' does NOT exist\n\n";
    }
}

// Alternative table name check
$stmt = $pdo->query("SHOW TABLES LIKE 'user_tenants'");
if ($stmt->rowCount() > 0) {
    echo "Note: Alternative table 'user_tenants' also exists\n\n";
}

// List all users
echo "=============================================================\n";
echo "Step 4: All Users in Database\n";
echo "=============================================================\n\n";

try {
    $stmt = $pdo->query("
        SELECT
            u.id,
            u.email,
            u.first_name,
            u.last_name,
            u.role,
            u.status,
            u.is_system_admin,
            u.last_login,
            u.created_at
        FROM users u
        ORDER BY u.id
    ");

    $users = $stmt->fetchAll();

    if (empty($users)) {
        echo "⚠ No users found in database!\n";
        echo "Run setup_admin.php to create the admin user.\n";
    } else {
        echo "Found " . count($users) . " user(s):\n\n";

        foreach ($users as $user) {
            echo "----------------------------------------\n";
            echo "ID: {$user['id']}\n";
            echo "Email: {$user['email']}\n";
            // Username field doesn't exist, skip it
            echo "Name: {$user['first_name']} {$user['last_name']}\n";
            echo "Role: {$user['role']}\n";
            echo "Status: {$user['status']}\n";
            // is_active field doesn't exist
            echo "System Admin: " . ($user['is_system_admin'] ? 'Yes' : 'No') . "\n";
            echo "Last Login: " . ($user['last_login'] ?: 'Never') . "\n";
            echo "Created: {$user['created_at']}\n";

            // Show tenant associations
            $stmt2 = $pdo->prepare("
                SELECT t.code, t.name, uta.role, uta.is_default
                FROM user_tenant_associations uta
                JOIN tenants t ON t.id = uta.tenant_id
                WHERE uta.user_id = ?
            ");
            $stmt2->execute([$user['id']]);
            $tenants = $stmt2->fetchAll();

            if (!empty($tenants)) {
                echo "Tenants:\n";
                foreach ($tenants as $tenant) {
                    echo "  - {$tenant['name']} ({$tenant['code']}) - Role: {$tenant['role']}";
                    if ($tenant['is_default']) {
                        echo " [DEFAULT]";
                    }
                    echo "\n";
                }
            } else {
                echo "Tenants: None\n";
            }
        }
    }
} catch (PDOException $e) {
    echo "Error fetching users: " . $e->getMessage() . "\n";
}

// Test admin password
echo "\n=============================================================\n";
echo "Step 5: Testing Admin Password Verification\n";
echo "=============================================================\n\n";

$adminEmail = 'asamodeo@fortibyte.it';
$testPassword = 'Ricord@1991';

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    $admin = $stmt->fetch();

    if ($admin) {
        echo "Admin user found:\n";
        echo "  Email: {$admin['email']}\n";
        echo "  Role: {$admin['role']}\n";
        echo "  Status: {$admin['status']}\n";
        echo "  System Admin: " . ($admin['is_system_admin'] ? 'Yes' : 'No') . "\n\n";

        echo "Testing password '$testPassword'...\n";

        // Test password verification
        if (password_verify($testPassword, $admin['password'])) {
            echo "✓ PASSWORD VERIFICATION SUCCESSFUL!\n\n";
            echo "The admin can login with:\n";
            echo "  Email: $adminEmail\n";
            echo "  Password: $testPassword\n";
        } else {
            echo "✗ PASSWORD VERIFICATION FAILED!\n\n";

            // Show hash info
            $hashInfo = password_get_info($admin['password']);
            echo "Current hash algorithm: " . $hashInfo['algoName'] . "\n";

            // Test if it needs rehashing
            if (password_needs_rehash($admin['password'], PASSWORD_BCRYPT)) {
                echo "⚠ Password hash needs to be updated.\n";
                echo "Run setup_admin.php to fix this.\n";
            }

            // Try alternative test
            echo "\nTesting if stored hash is valid...\n";
            if (substr($admin['password'], 0, 4) === '$2y$') {
                echo "✓ Hash format appears to be bcrypt\n";
            } else {
                echo "✗ Hash format is not recognized as bcrypt\n";
            }
        }

        // Additional login requirements check
        echo "\n----------------------------------------\n";
        echo "Login Requirements Check:\n";
        echo "----------------------------------------\n";

        $canLogin = true;

        // Check status
        if ($admin['status'] !== 'active') {
            echo "✗ User status is not 'active' (current: {$admin['status']})\n";
            $canLogin = false;
        } else {
            echo "✓ User status is 'active'\n";
        }

        // Note: is_active field doesn't exist in this database structure
        // Status field is used instead

        // Check locked_until
        if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
            echo "✗ User is locked until: {$admin['locked_until']}\n";
            $canLogin = false;
        } else {
            echo "✓ User is not locked\n";
        }

        // Check role
        if (!in_array($admin['role'], ['admin', 'special_user', 'standard_user'])) {
            echo "⚠ User role '{$admin['role']}' may not be recognized\n";
        } else {
            echo "✓ User role '{$admin['role']}' is valid\n";
        }

        if ($canLogin && password_verify($testPassword, $admin['password'])) {
            echo "\n✅ ADMIN CAN LOGIN SUCCESSFULLY!\n";
        } else {
            echo "\n⛔ ADMIN CANNOT LOGIN - Issues found above need to be fixed.\n";
        }

    } else {
        echo "✗ Admin user not found!\n";
        echo "Run setup_admin.php to create the admin user.\n";
    }
} catch (PDOException $e) {
    echo "Error checking admin: " . $e->getMessage() . "\n";
}

// Show all tenants
echo "\n=============================================================\n";
echo "Step 6: All Tenants in Database\n";
echo "=============================================================\n\n";

try {
    $stmt = $pdo->query("SELECT * FROM tenants ORDER BY id");
    $tenants = $stmt->fetchAll();

    if (empty($tenants)) {
        echo "⚠ No tenants found in database!\n";
    } else {
        echo "Found " . count($tenants) . " tenant(s):\n\n";

        foreach ($tenants as $tenant) {
            echo "ID: {$tenant['id']} | Code: {$tenant['code']} | Name: {$tenant['name']} | Status: {$tenant['status']}\n";

            // Count users in this tenant
            $stmt2 = $pdo->prepare("SELECT COUNT(*) as count FROM user_tenant_associations WHERE tenant_id = ?");
            $stmt2->execute([$tenant['id']]);
            $userCount = $stmt2->fetch()['count'];
            echo "  Users: $userCount\n\n";
        }
    }
} catch (PDOException $e) {
    echo "Error fetching tenants: " . $e->getMessage() . "\n";
}

// Summary
echo "=============================================================\n";
echo "Test Summary\n";
echo "=============================================================\n\n";

echo "Database: $dbName\n";
echo "Status: Connected\n";

if (isset($admin) && password_verify($testPassword, $admin['password'])) {
    echo "Admin Login: ✅ WORKING\n";
    echo "\nYou can now login at:\n";
    echo "http://localhost/Nexiosolution/collabora/\n\n";
    echo "Credentials:\n";
    echo "  Email: $adminEmail\n";
    echo "  Password: $testPassword\n";
} else {
    echo "Admin Login: ⛔ NOT WORKING\n";
    echo "\nPlease run: php setup_admin.php\n";
    echo "to create or fix the admin user.\n";
}

echo "\n=============================================================\n";
echo "Test completed!\n";
echo "=============================================================\n";