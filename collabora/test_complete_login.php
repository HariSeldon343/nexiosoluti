<?php
/**
 * Comprehensive Login Test Script
 * Tests the entire login flow including redirect functionality
 */

// Start the session (required for authentication)
session_start();

// Test configuration
$testResults = [];
$baseUrl = 'http://localhost/Nexiosolution/collabora';

// Function to add test result
function addResult($test, $status, $details = '') {
    global $testResults;
    $testResults[] = [
        'test' => $test,
        'status' => $status,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Function to make API call
function testApiCall($endpoint, $data) {
    $url = "http://localhost/Nexiosolution/collabora/api/$endpoint";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookie.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookie.txt');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'response' => json_decode($response, true),
        'raw' => $response
    ];
}

// Test 1: Check if all required files exist
echo "🔍 Testing file existence...\n";

$requiredFiles = [
    'index_v2.php' => 'Login page',
    'home_v2.php' => 'Home page',
    'admin/index.php' => 'Admin dashboard',
    'api/auth_simple.php' => 'Auth API endpoint',
    'assets/js/auth_v2.js' => 'Auth JavaScript',
    'assets/js/post-login-config.js' => 'Post-login config',
    'assets/js/post-login-handler.js' => 'Post-login handler',
    'includes/SimpleAuth.php' => 'SimpleAuth class',
    'includes/db.php' => 'Database connection'
];

foreach ($requiredFiles as $file => $description) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        addResult("File: $description", 'PASS', "$file exists");
        echo "✅ $description exists\n";
    } else {
        addResult("File: $description", 'FAIL', "$file not found");
        echo "❌ $description missing: $file\n";
    }
}

// Test 2: Test API connectivity
echo "\n🔍 Testing API connectivity...\n";

$testResult = testApiCall('auth_simple.php', ['action' => 'test']);
if ($testResult['code'] == 200) {
    addResult('API Test Endpoint', 'PASS', 'API is responding');
    echo "✅ API endpoint is working\n";
} else {
    addResult('API Test Endpoint', 'FAIL', "HTTP {$testResult['code']}");
    echo "❌ API endpoint not working (HTTP {$testResult['code']})\n";
}

// Test 3: Test login with correct credentials
echo "\n🔍 Testing login with correct credentials...\n";

$loginResult = testApiCall('auth_simple.php', [
    'action' => 'login',
    'email' => 'asamodeo@fortibyte.it',
    'password' => 'Ricord@1991'
]);

if ($loginResult['code'] == 200 && $loginResult['response']['success']) {
    addResult('Login with correct credentials', 'PASS', 'Login successful');
    echo "✅ Login successful\n";

    // Check redirect field
    if (isset($loginResult['response']['redirect'])) {
        $redirect = $loginResult['response']['redirect'];
        addResult('Redirect field present', 'PASS', "Redirect to: $redirect");
        echo "✅ Redirect field present: $redirect\n";

        // Verify redirect target exists
        $redirectPath = str_replace('/Nexiosolution/collabora/', '', $redirect);
        $fullRedirectPath = __DIR__ . '/' . $redirectPath;

        if (file_exists($fullRedirectPath)) {
            addResult('Redirect target exists', 'PASS', "$redirectPath exists");
            echo "✅ Redirect target exists: $redirectPath\n";
        } else {
            addResult('Redirect target exists', 'FAIL', "$redirectPath not found");
            echo "❌ Redirect target missing: $redirectPath\n";
        }
    } else {
        addResult('Redirect field present', 'FAIL', 'No redirect field in response');
        echo "❌ No redirect field in response\n";
    }

    // Check user data
    if (isset($loginResult['response']['user'])) {
        $user = $loginResult['response']['user'];
        echo "📋 User data:\n";
        echo "   - Email: {$user['email']}\n";
        echo "   - Role: {$user['role']}\n";
        echo "   - Is Admin: " . ($user['is_admin'] ? 'Yes' : 'No') . "\n";
    }

    // Check session
    if (isset($loginResult['response']['session_id'])) {
        echo "   - Session ID: {$loginResult['response']['session_id']}\n";
    }

} else {
    addResult('Login with correct credentials', 'FAIL', $loginResult['response']['error']['message'] ?? 'Unknown error');
    echo "❌ Login failed: " . ($loginResult['response']['error']['message'] ?? 'Unknown error') . "\n";
}

// Test 4: Test login with incorrect credentials
echo "\n🔍 Testing login with incorrect credentials...\n";

$failLoginResult = testApiCall('auth_simple.php', [
    'action' => 'login',
    'email' => 'asamodeo@fortibyte.it',
    'password' => 'wrongpassword'
]);

if ($failLoginResult['code'] == 401) {
    addResult('Login with wrong password', 'PASS', 'Correctly rejected (401)');
    echo "✅ Correctly rejected with 401 status\n";
} else {
    addResult('Login with wrong password', 'FAIL', "Expected 401, got {$failLoginResult['code']}");
    echo "❌ Expected 401 status, got {$failLoginResult['code']}\n";
}

// Test 5: Check database connection
echo "\n🔍 Testing database connection...\n";

require_once __DIR__ . '/includes/db.php';

try {
    $db = getDbConnection();
    if ($db) {
        addResult('Database connection', 'PASS', 'Connected successfully');
        echo "✅ Database connected\n";

        // Check for admin user
        $stmt = $db->prepare("SELECT id, email, role FROM users WHERE email = ?");
        $stmt->execute(['asamodeo@fortibyte.it']);
        $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($adminUser) {
            addResult('Admin user exists', 'PASS', "ID: {$adminUser['id']}, Role: {$adminUser['role']}");
            echo "✅ Admin user exists in database\n";
        } else {
            addResult('Admin user exists', 'FAIL', 'User not found in database');
            echo "❌ Admin user not found in database\n";
        }
    }
} catch (Exception $e) {
    addResult('Database connection', 'FAIL', $e->getMessage());
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

// Test 6: JavaScript redirect configuration
echo "\n🔍 Checking JavaScript configuration...\n";

// Read index_v2.php to check script order
$indexContent = file_get_contents(__DIR__ . '/index_v2.php');

// Check if post-login scripts are loaded before auth_v2.js
if (strpos($indexContent, 'post-login-config.js') !== false &&
    strpos($indexContent, 'post-login-handler.js') !== false) {

    $configPos = strpos($indexContent, 'post-login-config.js');
    $handlerPos = strpos($indexContent, 'post-login-handler.js');
    $authPos = strpos($indexContent, 'auth_v2.js');

    if ($configPos < $authPos && $handlerPos < $authPos) {
        addResult('Script loading order', 'PASS', 'Post-login scripts load before auth_v2.js');
        echo "✅ Scripts are loaded in correct order\n";
    } else {
        addResult('Script loading order', 'WARNING', 'Scripts may not be in optimal order');
        echo "⚠️ Scripts may not be loaded in optimal order\n";
    }
} else {
    addResult('Script loading order', 'FAIL', 'Post-login scripts not found');
    echo "❌ Post-login scripts not found in index_v2.php\n";
}

// Generate summary
echo "\n" . str_repeat('=', 60) . "\n";
echo "📊 TEST SUMMARY\n";
echo str_repeat('=', 60) . "\n\n";

$passCount = 0;
$failCount = 0;
$warnCount = 0;

foreach ($testResults as $result) {
    $icon = '✅';
    if ($result['status'] === 'FAIL') {
        $icon = '❌';
        $failCount++;
    } elseif ($result['status'] === 'WARNING') {
        $icon = '⚠️';
        $warnCount++;
    } else {
        $passCount++;
    }

    echo "$icon {$result['test']}: {$result['status']}\n";
    if ($result['details']) {
        echo "   Details: {$result['details']}\n";
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
echo "Results: $passCount passed, $failCount failed, $warnCount warnings\n";

// Overall status
if ($failCount === 0) {
    echo "\n🎉 All critical tests passed! The login system should work correctly.\n";
    echo "✅ You can now test the login at: $baseUrl/index_v2.php\n";
} else {
    echo "\n⚠️ Some tests failed. Please fix the issues above.\n";
}

echo "\n📝 NEXT STEPS:\n";
echo "1. Open browser: $baseUrl/index_v2.php\n";
echo "2. Login with: asamodeo@fortibyte.it / Ricord@1991\n";
echo "3. You should be redirected to: /admin/index.php (admin role)\n";
echo "\nTest completed at: " . date('Y-m-d H:i:s') . "\n";
?>