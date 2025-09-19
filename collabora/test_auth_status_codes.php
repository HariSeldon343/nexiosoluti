<?php
/**
 * Test Authentication Status Codes
 * Verifies that the API returns correct HTTP status codes
 */

// Colors for terminal output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[0;33m");
define('BLUE', "\033[0;34m");
define('RESET', "\033[0m");

echo BLUE . "\n=== Authentication API Status Code Test ===\n" . RESET;

// Test cases
$tests = [
    [
        'name' => 'Missing action field',
        'data' => ['email' => 'test@test.com', 'password' => 'password'],
        'expected_code' => 400,
        'expected_error_code' => 'missing_field'
    ],
    [
        'name' => 'Missing email field',
        'data' => ['action' => 'login', 'password' => 'password'],
        'expected_code' => 400,
        'expected_error_code' => 'missing_fields'
    ],
    [
        'name' => 'Missing password field',
        'data' => ['action' => 'login', 'email' => 'test@test.com'],
        'expected_code' => 400,
        'expected_error_code' => 'missing_fields'
    ],
    [
        'name' => 'Invalid action',
        'data' => ['action' => 'invalid_action', 'email' => 'test@test.com', 'password' => 'password'],
        'expected_code' => 400,
        'expected_error_code' => 'invalid_action'
    ],
    [
        'name' => 'Invalid credentials (wrong email)',
        'data' => ['action' => 'login', 'email' => 'wrong@email.com', 'password' => 'Ricord@1991'],
        'expected_code' => 401,
        'expected_error_code' => 'invalid_credentials'
    ],
    [
        'name' => 'Invalid credentials (wrong password)',
        'data' => ['action' => 'login', 'email' => 'asamodeo@fortibyte.it', 'password' => 'wrong_password'],
        'expected_code' => 401,
        'expected_error_code' => 'invalid_credentials'
    ],
    [
        'name' => 'Valid credentials',
        'data' => ['action' => 'login', 'email' => 'asamodeo@fortibyte.it', 'password' => 'Ricord@1991'],
        'expected_code' => 200,
        'expected_success' => true
    ]
];

// Function to make API call
function testApiCall($data) {
    // Set up the context for the request
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';

    // Prepare input
    $input = json_encode($data);

    // Capture output
    ob_start();

    // Mock php://input
    $tempFile = tempnam(sys_get_temp_dir(), 'test_input');
    file_put_contents($tempFile, $input);

    // Save current directory and change to API directory
    $currentDir = getcwd();
    chdir(__DIR__ . '/api');

    // Override the input stream
    stream_wrapper_unregister("php");
    stream_wrapper_register("php", "MockPhpStream");
    MockPhpStream::$data = $input;

    // Include the API file
    $httpCode = 200; // Default
    try {
        // Capture headers
        $headers = [];

        // Override header function
        eval('
            namespace {
                function header($header) {
                    global $headers;
                    $headers[] = $header;
                    if (strpos($header, "HTTP/") === 0 || preg_match("/^Status:\s*(\d+)/", $header, $matches)) {
                        // Extract status code
                    }
                    return true;
                }
                function http_response_code($code = null) {
                    global $httpCode;
                    if ($code !== null) {
                        $httpCode = $code;
                    }
                    return $httpCode;
                }
            }
        ');

        // Include the file
        include __DIR__ . '/api/auth_simple.php';

    } catch (Exception $e) {
        // Handle exceptions
    }

    // Get output
    $output = ob_get_clean();

    // Restore
    stream_wrapper_restore("php");
    chdir($currentDir);

    // Clean up
    unlink($tempFile);

    return [
        'code' => $httpCode,
        'body' => json_decode($output, true),
        'raw' => $output
    ];
}

// Alternative: Direct PHP execution
function testApiDirect($data) {
    $script = __DIR__ . '/api/auth_simple.php';
    $json = json_encode($data);

    // Create a test script that simulates the request
    $testScript = <<<'PHP'
<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Mock php://input
class MockInput {
    public static $data = '';
    private $position = 0;

    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }

    public function stream_read($count) {
        $ret = substr(self::$data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_eof() {
        return $this->position >= strlen(self::$data);
    }

    public function stream_stat() {
        return [];
    }

    public function stream_tell() {
        return $this->position;
    }
}

stream_wrapper_unregister("php");
stream_wrapper_register("php", "MockInput");
MockInput::$data = '%INPUT%';

// Capture HTTP response code
$httpCode = 200;
$originalHeaderFunc = function($header) use (&$httpCode) {
    if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
        $httpCode = (int)$matches[1];
    }
};

// Override http_response_code
function http_response_code($code = null) {
    global $httpCode;
    if ($code !== null) {
        $httpCode = $code;
    }
    return $httpCode;
}

ob_start();
require '%SCRIPT%';
$output = ob_get_clean();

echo json_encode([
    'code' => $httpCode,
    'body' => $output
]);
PHP;

    $testScript = str_replace('%INPUT%', $json, $testScript);
    $testScript = str_replace('%SCRIPT%', $script, $testScript);

    $tempFile = tempnam(sys_get_temp_dir(), 'test_auth_');
    file_put_contents($tempFile, $testScript);

    $result = shell_exec("php $tempFile 2>&1");
    unlink($tempFile);

    $decoded = json_decode($result, true);
    if ($decoded) {
        return [
            'code' => $decoded['code'],
            'body' => json_decode($decoded['body'], true),
            'raw' => $decoded['body']
        ];
    }

    return ['code' => 0, 'body' => null, 'raw' => $result];
}

// Run tests
$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    echo "\n" . YELLOW . "Test: " . $test['name'] . RESET . "\n";
    echo "Request: " . json_encode($test['data']) . "\n";

    // Make the API call directly
    $result = testApiDirect($test['data']);

    echo "Response Code: " . $result['code'];

    $codeMatch = ($result['code'] == $test['expected_code']);

    if ($codeMatch) {
        echo GREEN . " ✓" . RESET . "\n";
    } else {
        echo RED . " ✗ (Expected: " . $test['expected_code'] . ")" . RESET . "\n";
    }

    if ($result['body']) {
        if (isset($test['expected_error_code']) && isset($result['body']['error']['code'])) {
            $errorCodeMatch = ($result['body']['error']['code'] == $test['expected_error_code']);
            echo "Error Code: " . $result['body']['error']['code'];
            if ($errorCodeMatch) {
                echo GREEN . " ✓" . RESET . "\n";
            } else {
                echo RED . " ✗ (Expected: " . $test['expected_error_code'] . ")" . RESET . "\n";
            }
        }

        if (isset($test['expected_success'])) {
            $successMatch = ($result['body']['success'] == $test['expected_success']);
            echo "Success: " . ($result['body']['success'] ? 'true' : 'false');
            if ($successMatch) {
                echo GREEN . " ✓" . RESET . "\n";
            } else {
                echo RED . " ✗" . RESET . "\n";
            }
        }

        // Show error message if present
        if (isset($result['body']['error']['message'])) {
            echo "Message: " . $result['body']['error']['message'] . "\n";
        }

        // Show fields if present
        if (isset($result['body']['error']['fields']) && !empty($result['body']['error']['fields'])) {
            echo "Missing Fields: " . implode(', ', $result['body']['error']['fields']) . "\n";
        }
    } else {
        echo "Response: " . substr($result['raw'], 0, 200) . "\n";
    }

    if ($codeMatch) {
        $passed++;
    } else {
        $failed++;
    }
}

// Summary
echo "\n" . BLUE . "=== Test Summary ===" . RESET . "\n";
echo GREEN . "Passed: $passed" . RESET . "\n";
if ($failed > 0) {
    echo RED . "Failed: $failed" . RESET . "\n";
}

// Mock stream wrapper for testing
class MockPhpStream {
    public static $data = '';
    private $position = 0;

    public function stream_open($path, $mode, $options, &$opened_path) {
        if ($path === 'php://input') {
            $this->position = 0;
            return true;
        }
        return false;
    }

    public function stream_read($count) {
        $ret = substr(self::$data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_eof() {
        return $this->position >= strlen(self::$data);
    }

    public function stream_stat() {
        return [];
    }
}

echo "\n";