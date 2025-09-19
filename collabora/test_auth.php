<?php declare(strict_types=1);

/**
 * Test authentication system
 */

// Prevent session start for testing
define('TESTING_MODE', true);

require_once 'config.php';
require_once 'includes/autoload.php';
require_once 'includes/auth_v2.php';

use Collabora\Auth\AuthenticationV2;

echo "<h1>Authentication System Test</h1>";
echo "<pre>";

try {
    echo "1. Testing class loading...\n";
    $auth = new AuthenticationV2();
    echo "   ✓ AuthenticationV2 class loaded successfully\n\n";

    echo "2. Testing database connection...\n";
    $db = getDbConnection();
    echo "   ✓ Database connection successful\n\n";

    echo "3. Testing admin user exists...\n";
    $stmt = $db->prepare("SELECT id, email, role, is_system_admin FROM users WHERE email = :email");
    $stmt->execute(['email' => 'asamodeo@fortibyte.it']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "   ✓ Admin user found:\n";
        echo "     - ID: {$user['id']}\n";
        echo "     - Email: {$user['email']}\n";
        echo "     - Role: {$user['role']}\n";
        echo "     - System Admin: " . ($user['is_system_admin'] ? 'Yes' : 'No') . "\n\n";
    } else {
        echo "   ✗ Admin user not found\n\n";
    }

    echo "4. Testing login functionality...\n";
    try {
        // Start session for login test
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $result = $auth->login('asamodeo@fortibyte.it', 'Ricord@1991');
        echo "   ✓ Login successful:\n";
        echo "     - User ID: {$result['user']['id']}\n";
        echo "     - Name: {$result['user']['first_name']} {$result['user']['last_name']}\n";
        echo "     - Role: {$result['user']['role']}\n";
        echo "     - Tenants available: " . count($result['tenants']) . "\n\n";

        echo "5. Testing authentication check...\n";
        if ($auth->isAuthenticated()) {
            echo "   ✓ User is authenticated\n\n";

            echo "6. Testing current user retrieval...\n";
            $currentUser = $auth->getCurrentUser();
            if ($currentUser) {
                echo "   ✓ Current user retrieved successfully\n";
                echo "     - ID: {$currentUser['id']}\n";
                echo "     - Email: {$currentUser['email']}\n\n";
            }

            echo "7. Testing admin check...\n";
            if ($auth->isAdmin()) {
                echo "   ✓ User is confirmed as admin\n\n";
            } else {
                echo "   ✗ User is not recognized as admin\n\n";
            }

            echo "8. Testing logout...\n";
            $auth->logout();
            echo "   ✓ Logout successful\n\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Login failed: " . $e->getMessage() . "\n\n";
    }

    echo "9. Checking required tables...\n";
    $requiredTables = ['users', 'tenants', 'user_tenant_associations', 'user_sessions', 'activity_logs'];
    foreach ($requiredTables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->fetch()) {
            echo "   ✓ Table '{$table}' exists\n";
        } else {
            echo "   ✗ Table '{$table}' missing\n";
        }
    }

    echo "\n<strong style='color: green;'>✓ All tests completed!</strong>\n";

} catch (Exception $e) {
    echo "\n<strong style='color: red;'>✗ Test failed: " . $e->getMessage() . "</strong>\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<p><a href='index_v2.php'>Go to Login Page</a></p>";