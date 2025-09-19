<?php
/**
 * Direct Test of Authentication API
 * Tests the actual API endpoints directly
 */

error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 0);

echo "\n=== Direct Authentication API Test ===\n\n";

// Test configuration
$baseUrl = 'http://localhost/Nexiosolution/collabora/api/auth_simple.php';

// Test cases
$tests = [
    [
        'name' => '1. Missing action field (should return 400)',
        'data' => ['email' => 'test@test.com', 'password' => 'password'],
        'expected_code' => 400
    ],
    [
        'name' => '2. Missing email (should return 400)',
        'data' => ['action' => 'login', 'password' => 'password'],
        'expected_code' => 400
    ],
    [
        'name' => '3. Missing password (should return 400)',
        'data' => ['action' => 'login', 'email' => 'test@test.com'],
        'expected_code' => 400
    ],
    [
        'name' => '4. Invalid credentials (should return 401)',
        'data' => ['action' => 'login', 'email' => 'wrong@email.com', 'password' => 'wrong'],
        'expected_code' => 401
    ],
    [
        'name' => '5. Valid credentials (should return 200)',
        'data' => ['action' => 'login', 'email' => 'asamodeo@fortibyte.it', 'password' => 'Ricord@1991'],
        'expected_code' => 200
    ]
];

// Function to simulate API call locally
function testApiLocally($data) {
    // Save current state
    $originalPost = $_POST;
    $originalRequest = $_REQUEST;
    $originalServer = $_SERVER;

    // Set up environment
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_POST = [];
    $_REQUEST = [];

    // Mock php://input
    $GLOBALS['test_input_data'] = json_encode($data);

    // Start output buffering
    ob_start();

    // Capture HTTP response code
    $httpCode = 200;

    try {
        // Override functions in global namespace
        eval('
        namespace {
            if (!function_exists("test_http_response_code")) {
                function test_http_response_code($code = null) {
                    global $httpCode;
                    if ($code !== null) {
                        $httpCode = $code;
                    }
                    return $httpCode;
                }
            }
        }
        ');

        // Create wrapper to intercept file_get_contents
        $code = '
        $input = $GLOBALS["test_input_data"];
        $data = json_decode($input, true);
        ';

        // Load the actual auth file content and modify it
        $authContent = file_get_contents(__DIR__ . '/api/auth_simple.php');

        // Replace file_get_contents call
        $authContent = str_replace(
            'file_get_contents(\'php://input\')',
            '$GLOBALS["test_input_data"]',
            $authContent
        );

        // Replace http_response_code with our test version
        $authContent = str_replace(
            'http_response_code(',
            'test_http_response_code(',
            $authContent
        );

        // Remove opening PHP tag and execute
        $authContent = str_replace('<?php', '', $authContent);

        // Execute modified code
        eval($authContent);

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }

    $output = ob_get_clean();

    // Restore original state
    $_POST = $originalPost;
    $_REQUEST = $originalRequest;
    $_SERVER = $originalServer;

    return [
        'code' => $httpCode,
        'body' => json_decode($output, true),
        'raw' => $output
    ];
}

// Run tests
echo "Testing authentication endpoint status codes:\n";
echo str_repeat('-', 60) . "\n\n";

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    echo $test['name'] . "\n";
    echo "Request: " . json_encode($test['data']) . "\n";

    $result = testApiLocally($test['data']);

    echo "Response Code: " . $result['code'];

    if ($result['code'] == $test['expected_code']) {
        echo " ✓\n";
        $passed++;
    } else {
        echo " ✗ (Expected: " . $test['expected_code'] . ")\n";
        $failed++;
    }

    if ($result['body']) {
        if (isset($result['body']['error'])) {
            if (is_array($result['body']['error'])) {
                echo "Error Code: " . ($result['body']['error']['code'] ?? 'N/A') . "\n";
                echo "Message: " . ($result['body']['error']['message'] ?? 'N/A') . "\n";
                if (!empty($result['body']['error']['fields'])) {
                    echo "Fields: " . implode(', ', $result['body']['error']['fields']) . "\n";
                }
            } else {
                echo "Error: " . $result['body']['error'] . "\n";
            }
        } elseif ($result['body']['success']) {
            echo "Success: Login successful\n";
            if (isset($result['body']['user'])) {
                echo "User: " . $result['body']['user']['email'] . " (" . $result['body']['user']['role'] . ")\n";
            }
        }
    }

    echo "\n";
}

// Summary
echo str_repeat('-', 60) . "\n";
echo "Test Summary:\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed == 0) {
    echo "\n✓ All tests passed! Status codes are working correctly.\n";
} else {
    echo "\n✗ Some tests failed. Please check the implementation.\n";
}

echo "\n";