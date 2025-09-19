<?php
/**
 * Simple test to verify API status codes
 * Run this file directly to test the authentication API
 */

// Suppress warnings
error_reporting(E_ERROR | E_PARSE);

echo "\n=== Testing Authentication API Status Codes ===\n\n";

// Change to the collabora directory
chdir(__DIR__);

// Test data
$tests = [
    [
        'name' => 'Test 1: Missing action (expect 400)',
        'input' => json_encode(['email' => 'test@test.com', 'password' => 'test']),
        'expected' => 400
    ],
    [
        'name' => 'Test 2: Missing email (expect 400)',
        'input' => json_encode(['action' => 'login', 'password' => 'test']),
        'expected' => 400
    ],
    [
        'name' => 'Test 3: Missing password (expect 400)',
        'input' => json_encode(['action' => 'login', 'email' => 'test@test.com']),
        'expected' => 400
    ],
    [
        'name' => 'Test 4: Invalid credentials (expect 401)',
        'input' => json_encode(['action' => 'login', 'email' => 'invalid@test.com', 'password' => 'wrong']),
        'expected' => 401
    ],
    [
        'name' => 'Test 5: Valid login (expect 200)',
        'input' => json_encode(['action' => 'login', 'email' => 'asamodeo@fortibyte.it', 'password' => 'Ricord@1991']),
        'expected' => 200
    ]
];

// Run each test
$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    echo $test['name'] . "\n";

    // Create temporary file with input
    $tempInput = tempnam(sys_get_temp_dir(), 'input');
    file_put_contents($tempInput, $test['input']);

    // Prepare the PHP command
    $cmd = sprintf(
        'cd %s && %s -r %s < %s 2>&1',
        escapeshellarg(__DIR__),
        escapeshellarg('C:\xampp\php\php.exe'),
        escapeshellarg('
            $_SERVER["REQUEST_METHOD"] = "POST";
            $_SERVER["CONTENT_TYPE"] = "application/json";
            $GLOBALS["test_response_code"] = 200;
            function http_response_code($code = null) {
                global $test_response_code;
                if ($code !== null) $test_response_code = $code;
                return $test_response_code;
            }
            require "api/auth_simple.php";
            echo "\nSTATUS_CODE:" . $test_response_code;
        '),
        escapeshellarg($tempInput)
    );

    // Execute the command
    $output = shell_exec($cmd);

    // Extract status code
    $statusCode = 0;
    if (preg_match('/STATUS_CODE:(\d+)/', $output, $matches)) {
        $statusCode = (int)$matches[1];
    }

    // Check result
    if ($statusCode === $test['expected']) {
        echo "  ✓ Status: $statusCode (as expected)\n";
        $passed++;
    } else {
        echo "  ✗ Status: $statusCode (expected: {$test['expected']})\n";
        $failed++;
    }

    // Parse JSON response (if any)
    $jsonStart = strpos($output, '{');
    if ($jsonStart !== false) {
        $jsonEnd = strrpos($output, '}');
        if ($jsonEnd !== false) {
            $json = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
            $response = json_decode($json, true);
            if ($response) {
                if (isset($response['error'])) {
                    if (is_array($response['error'])) {
                        echo "  Error code: " . $response['error']['code'] . "\n";
                        echo "  Message: " . $response['error']['message'] . "\n";
                        if (!empty($response['error']['fields'])) {
                            echo "  Missing fields: " . implode(', ', $response['error']['fields']) . "\n";
                        }
                    } else {
                        echo "  Error: " . $response['error'] . "\n";
                    }
                } elseif (isset($response['success']) && $response['success']) {
                    echo "  Success: Login successful\n";
                }
            }
        }
    }

    echo "\n";

    // Clean up
    unlink($tempInput);
}

// Summary
echo "----------------------------------------\n";
echo "Results:\n";
echo "  Passed: $passed\n";
echo "  Failed: $failed\n";

if ($failed === 0) {
    echo "\n✅ All tests passed! Status codes are working correctly.\n";
} else {
    echo "\n❌ Some tests failed. Please review the implementation.\n";
}

echo "\nNote: To see the full API contract, visit:\n";
echo "http://localhost/Nexiosolution/collabora/api/auth_debug.php\n";
echo "\n";