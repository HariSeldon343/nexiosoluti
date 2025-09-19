<?php
// Test script per verificare il login dopo il fix
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Test Login Script ===\n";
echo "Testing fix for 'Cannot redeclare getDbConnection()' error\n\n";

// Include the required files in the correct order
echo "1. Including config_v2.php...\n";
require_once __DIR__ . '/config_v2.php';
echo "   ✓ config_v2.php loaded successfully\n";

echo "2. Including autoload.php...\n";
require_once __DIR__ . '/includes/autoload.php';
echo "   ✓ autoload.php loaded successfully\n";

echo "3. Including auth_v2.php...\n";
require_once __DIR__ . '/includes/auth_v2.php';
echo "   ✓ auth_v2.php loaded successfully\n";

echo "\n4. Testing database connection...\n";
try {
    $db = getDbConnection();
    echo "   ✓ Database connection successful\n";

    // Test query
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    if ($result['test'] == 1) {
        echo "   ✓ Database query test successful\n";
    }
} catch (Exception $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n";
}

echo "\n5. Testing authentication with provided credentials...\n";
use Collabora\Auth\AuthenticationV2;

try {
    $auth = new AuthenticationV2();
    echo "   ✓ Authentication class instantiated\n";

    // Test login
    $email = 'asamodeo@fortibyte.it';
    $password = 'Ricord@1991';

    echo "   Testing login for: $email\n";

    $loginResult = $auth->login($email, $password);

    if ($loginResult['success']) {
        echo "   ✓ Login successful!\n";
        echo "   User: " . $loginResult['user']['nome'] . " " . $loginResult['user']['cognome'] . "\n";
        echo "   Role: " . $loginResult['user']['role'] . "\n";
        echo "   Tenant: " . $loginResult['user']['tenant_id'] . "\n";
    } else {
        echo "   ✗ Login failed: " . $loginResult['error'] . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Authentication error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "All includes are working correctly. No duplicate function declarations!\n";
?>