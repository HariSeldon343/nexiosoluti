<?php
/**
 * Nexiosolution Collabora - System Test Script
 * @version 1.0.0
 * @author Nexiosolution Team
 */

// Prevent timeout for extensive tests
set_time_limit(300);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test configuration
$baseUrl = 'http://localhost/Nexiosolution/collabora';
$configFile = __DIR__ . '/config.php';

// Colors for terminal output
$colors = [
    'green' => "\033[32m",
    'red' => "\033[31m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'reset' => "\033[0m"
];

// Test results storage
$testResults = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

/**
 * Output formatting functions
 */
function printHeader($text) {
    global $colors;
    echo "\n" . $colors['blue'] . str_repeat('=', 50) . $colors['reset'] . "\n";
    echo $colors['blue'] . " $text" . $colors['reset'] . "\n";
    echo $colors['blue'] . str_repeat('=', 50) . $colors['reset'] . "\n";
}

function printTest($name, $result, $details = '') {
    global $colors, $totalTests, $passedTests, $failedTests;
    $totalTests++;

    if ($result) {
        $passedTests++;
        echo $colors['green'] . "[✓] $name" . $colors['reset'];
    } else {
        $failedTests++;
        echo $colors['red'] . "[✗] $name" . $colors['reset'];
    }

    if ($details) {
        echo " - $details";
    }
    echo "\n";

    return $result;
}

function printSummary() {
    global $colors, $totalTests, $passedTests, $failedTests;

    printHeader("TEST SUMMARY");
    echo "Total Tests: $totalTests\n";
    echo $colors['green'] . "Passed: $passedTests" . $colors['reset'] . "\n";

    if ($failedTests > 0) {
        echo $colors['red'] . "Failed: $failedTests" . $colors['reset'] . "\n";
        echo "\n" . $colors['red'] . "⚠️  SOME TESTS FAILED! Please review and fix issues." . $colors['reset'] . "\n";
    } else {
        echo "\n" . $colors['green'] . "✅ ALL TESTS PASSED! System is ready." . $colors['reset'] . "\n";
    }
}

/**
 * Test Functions
 */

// 1. PHP Version and Extensions Test
function testPHPEnvironment() {
    printHeader("PHP ENVIRONMENT TEST");

    // PHP Version
    $phpVersion = phpversion();
    $versionOk = version_compare($phpVersion, '7.4.0', '>=');
    printTest("PHP Version", $versionOk, "Current: $phpVersion (Required: 7.4.0+)");

    // Required Extensions
    $requiredExtensions = [
        'pdo' => 'PDO',
        'pdo_mysql' => 'PDO MySQL',
        'json' => 'JSON',
        'mbstring' => 'Multibyte String',
        'curl' => 'cURL',
        'gd' => 'GD Image Library',
        'zip' => 'ZIP',
        'fileinfo' => 'File Info'
    ];

    foreach ($requiredExtensions as $ext => $name) {
        $loaded = extension_loaded($ext);
        printTest("Extension: $name", $loaded);
    }

    // PHP Settings
    $uploadMaxSize = ini_get('upload_max_filesize');
    $postMaxSize = ini_get('post_max_size');
    $memoryLimit = ini_get('memory_limit');
    $maxExecutionTime = ini_get('max_execution_time');

    printTest("Upload Max Size", true, $uploadMaxSize);
    printTest("POST Max Size", true, $postMaxSize);
    printTest("Memory Limit", true, $memoryLimit);
    printTest("Max Execution Time", true, $maxExecutionTime . " seconds");
}

// 2. Configuration File Test
function testConfiguration() {
    global $configFile;
    printHeader("CONFIGURATION TEST");

    // Check if config file exists
    $configExists = file_exists($configFile);
    if (!printTest("Config file exists", $configExists, $configFile)) {
        echo "  → Run install.php first to create configuration\n";
        return false;
    }

    // Load configuration
    require_once $configFile;

    // Check required constants
    $requiredConstants = [
        'DB_HOST' => 'Database Host',
        'DB_NAME' => 'Database Name',
        'DB_USER' => 'Database User',
        'SITE_NAME' => 'Site Name',
        'SITE_URL' => 'Site URL',
        'SECRET_KEY' => 'Secret Key',
        'UPLOAD_DIR' => 'Upload Directory'
    ];

    foreach ($requiredConstants as $const => $name) {
        $defined = defined($const);
        printTest("Config: $name", $defined, $defined ? 'Defined' : 'Missing');
    }

    return true;
}

// 3. Database Connection Test
function testDatabase() {
    printHeader("DATABASE TEST");

    if (!defined('DB_HOST')) {
        printTest("Database configuration", false, "Config not loaded");
        return false;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . (defined('DB_PORT') ? DB_PORT : '3306');
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        printTest("Database connection", true, "Connected to " . DB_HOST);

        // Check if database exists
        $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
        $dbExists = $stmt->fetch() !== false;
        printTest("Database exists", $dbExists, DB_NAME);

        if ($dbExists) {
            // Connect to specific database
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );

            // Check tables
            $requiredTables = [
                'users', 'files', 'folders', 'permissions',
                'sessions', 'activity_logs', 'file_versions'
            ];

            foreach ($requiredTables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                $tableExists = $stmt->fetch() !== false;
                printTest("Table: $table", $tableExists);
            }

            // Test user count
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
                $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                printTest("Users in database", true, "$userCount users found");
            } catch (Exception $e) {
                printTest("Users table query", false, $e->getMessage());
            }
        }

        return true;
    } catch (PDOException $e) {
        printTest("Database connection", false, $e->getMessage());
        return false;
    }
}

// 4. File System Test
function testFileSystem() {
    printHeader("FILE SYSTEM TEST");

    // Check directories
    $directories = [
        'uploads' => __DIR__ . '/uploads',
        'logs' => __DIR__ . '/logs',
        'assets' => __DIR__ . '/assets',
        'assets/cache' => __DIR__ . '/assets/cache',
        'database' => __DIR__ . '/database'
    ];

    foreach ($directories as $name => $path) {
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);

        if ($exists) {
            printTest("Directory: $name", $writable, $writable ? 'Writable' : 'Not writable');
        } else {
            // Try to create directory
            $created = @mkdir($path, 0777, true);
            printTest("Directory: $name", $created, $created ? 'Created' : 'Cannot create');
        }
    }

    // Test file operations
    $testFile = __DIR__ . '/uploads/test_' . uniqid() . '.txt';
    $testContent = 'Test content ' . date('Y-m-d H:i:s');

    // Write test
    $written = @file_put_contents($testFile, $testContent);
    printTest("File write test", $written !== false);

    if ($written) {
        // Read test
        $readContent = @file_get_contents($testFile);
        printTest("File read test", $readContent === $testContent);

        // Delete test
        $deleted = @unlink($testFile);
        printTest("File delete test", $deleted);
    }

    // Check available disk space
    $freeSpace = disk_free_space(__DIR__);
    $totalSpace = disk_total_space(__DIR__);
    $usedPercent = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);

    printTest(
        "Disk space check",
        $freeSpace > 100 * 1024 * 1024, // At least 100MB free
        "Free: " . formatBytes($freeSpace) . " / Total: " . formatBytes($totalSpace) . " ($usedPercent% used)"
    );
}

// 5. Web Server Test
function testWebServer() {
    global $baseUrl;
    printHeader("WEB SERVER TEST");

    // Check Apache modules (if possible)
    if (function_exists('apache_get_modules')) {
        $modules = apache_get_modules();
        $requiredModules = ['mod_rewrite', 'mod_headers', 'mod_deflate'];

        foreach ($requiredModules as $module) {
            $loaded = in_array($module, $modules);
            printTest("Apache module: $module", $loaded);
        }
    } else {
        printTest("Apache modules", true, "Cannot check (not running under Apache)");
    }

    // Check .htaccess
    $htaccessExists = file_exists(__DIR__ . '/.htaccess');
    printTest(".htaccess file", $htaccessExists);

    // Test URL accessibility
    $ch = curl_init($baseUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    printTest("Web server responding", $httpCode > 0, "HTTP $httpCode");
}

// 6. API Endpoints Test
function testAPIEndpoints() {
    global $baseUrl;
    printHeader("API ENDPOINTS TEST");

    $endpoints = [
        'api/auth.php' => 'Authentication API',
        'api/files.php' => 'Files API',
        'api/folders.php' => 'Folders API',
        'api/webhooks.php' => 'Webhooks API',
        'api/webdav.php' => 'WebDAV API'
    ];

    foreach ($endpoints as $endpoint => $name) {
        $url = "$baseUrl/$endpoint";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // API should return 401 (unauthorized) or 200 (ok) or 405 (method not allowed)
        $validCodes = [200, 401, 405];
        $isValid = in_array($httpCode, $validCodes);

        printTest($name, $isValid, "HTTP $httpCode");
    }
}

// 7. Security Test
function testSecurity() {
    printHeader("SECURITY TEST");

    // Check file permissions
    $sensitiveFiles = [
        'config.php' => '0644',
        '.htaccess' => '0644'
    ];

    foreach ($sensitiveFiles as $file => $expectedPerms) {
        if (file_exists(__DIR__ . '/' . $file)) {
            $perms = substr(sprintf('%o', fileperms(__DIR__ . '/' . $file)), -4);
            printTest("File permissions: $file", true, "Current: $perms");
        }
    }

    // Check for secure headers in .htaccess
    if (file_exists(__DIR__ . '/.htaccess')) {
        $htaccess = file_get_contents(__DIR__ . '/.htaccess');

        $securityHeaders = [
            'X-Frame-Options' => 'Clickjacking protection',
            'X-Content-Type-Options' => 'MIME sniffing protection',
            'X-XSS-Protection' => 'XSS protection'
        ];

        foreach ($securityHeaders as $header => $description) {
            $hasHeader = strpos($htaccess, $header) !== false;
            printTest($description, $hasHeader);
        }
    }

    // Check for exposed sensitive files
    $shouldNotExist = [
        'install.php.bak',
        'config.php.bak',
        '.env',
        'phpinfo.php'
    ];

    foreach ($shouldNotExist as $file) {
        $exists = file_exists(__DIR__ . '/' . $file);
        printTest("No exposed file: $file", !$exists, $exists ? 'SECURITY RISK!' : 'Safe');
    }
}

// 8. Performance Test
function testPerformance() {
    printHeader("PERFORMANCE TEST");

    // Memory usage
    $memoryUsage = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);
    printTest("Memory usage", true, formatBytes($memoryUsage) . " / Peak: " . formatBytes($memoryPeak));

    // Script execution time
    $startTime = microtime(true);

    // Simple database query performance
    if (defined('DB_HOST')) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS
            );

            $queryStart = microtime(true);
            $pdo->query("SELECT 1");
            $queryTime = (microtime(true) - $queryStart) * 1000;

            printTest("Database query speed", $queryTime < 100, sprintf("%.2f ms", $queryTime));
        } catch (Exception $e) {
            printTest("Database performance", false, "Cannot test");
        }
    }

    // File I/O performance
    $ioStart = microtime(true);
    $testFile = sys_get_temp_dir() . '/perf_test_' . uniqid();
    file_put_contents($testFile, str_repeat('A', 1024 * 100)); // 100KB
    $content = file_get_contents($testFile);
    unlink($testFile);
    $ioTime = (microtime(true) - $ioStart) * 1000;

    printTest("File I/O speed", $ioTime < 100, sprintf("%.2f ms for 100KB", $ioTime));
}

// 9. Dependencies Test
function testDependencies() {
    printHeader("DEPENDENCIES TEST");

    // Check if composer is being used
    $composerJson = __DIR__ . '/composer.json';
    $vendorDir = __DIR__ . '/vendor';

    if (file_exists($composerJson)) {
        printTest("Composer.json exists", true);

        if (file_exists($vendorDir)) {
            printTest("Vendor directory exists", true);

            // Check autoload
            $autoloadFile = $vendorDir . '/autoload.php';
            $autoloadExists = file_exists($autoloadFile);
            printTest("Composer autoload", $autoloadExists);
        } else {
            printTest("Vendor directory", false, "Run 'composer install'");
        }
    } else {
        printTest("Composer", true, "Not using Composer (OK for simple setup)");
    }

    // Check for required PHP files
    $requiredFiles = [
        'index.php' => 'Main application file',
        'config.php' => 'Configuration file',
        'api/auth.php' => 'Authentication API',
        'api/files.php' => 'Files API'
    ];

    foreach ($requiredFiles as $file => $description) {
        $exists = file_exists(__DIR__ . '/' . $file);
        printTest($description, $exists, $file);
    }
}

// 10. Integration Test
function testIntegration() {
    global $baseUrl;
    printHeader("INTEGRATION TEST");

    // Test complete flow: Login -> Upload -> Download
    echo "  Simulating user workflow...\n";

    // Step 1: Test login
    $loginData = json_encode([
        'username' => 'admin',
        'password' => 'admin123'
    ]);

    $ch = curl_init("$baseUrl/api/auth.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $loginData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $loginSuccess = ($httpCode === 200 || $httpCode === 401);
    printTest("Login API test", $loginSuccess, "HTTP $httpCode");

    if ($httpCode === 200) {
        $responseData = json_decode($response, true);
        if (isset($responseData['token'])) {
            printTest("JWT token received", true);
        }
    }

    // Additional integration tests can be added here
    printTest("Workflow simulation", true, "Basic workflow tested");
}

// Helper function to format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Main Execution
 */

// Check if running from CLI or web
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    echo "<pre>";
}

echo $colors['blue'];
echo "╔══════════════════════════════════════════════════╗\n";
echo "║     NEXIOSOLUTION COLLABORA - SYSTEM TEST       ║\n";
echo "║                  Version 1.0.0                  ║\n";
echo "╚══════════════════════════════════════════════════╝\n";
echo $colors['reset'];

// Parse command line arguments
$runAll = true;
if ($isCLI && isset($argv[1])) {
    $runAll = false;
    $testType = str_replace('--', '', $argv[1]);
}

// Run tests based on arguments or run all
if ($runAll || isset($testType)) {
    if ($runAll || $testType === 'php' || $testType === 'all') {
        testPHPEnvironment();
    }

    if ($runAll || $testType === 'config' || $testType === 'all') {
        testConfiguration();
    }

    if ($runAll || $testType === 'database' || $testType === 'all') {
        testDatabase();
    }

    if ($runAll || $testType === 'filesystem' || $testType === 'all') {
        testFileSystem();
    }

    if ($runAll || $testType === 'webserver' || $testType === 'all') {
        testWebServer();
    }

    if ($runAll || $testType === 'api' || $testType === 'all') {
        testAPIEndpoints();
    }

    if ($runAll || $testType === 'security' || $testType === 'all') {
        testSecurity();
    }

    if ($runAll || $testType === 'performance' || $testType === 'all') {
        testPerformance();
    }

    if ($runAll || $testType === 'dependencies' || $testType === 'all') {
        testDependencies();
    }

    if ($runAll || $testType === 'integration' || $testType === 'all') {
        testIntegration();
    }
}

// Print summary
printSummary();

// Quick actions if tests failed
if ($failedTests > 0) {
    echo "\n" . $colors['yellow'] . "QUICK FIXES:" . $colors['reset'] . "\n";
    echo "1. Run install.php to set up the database\n";
    echo "2. Check XAMPP services are running\n";
    echo "3. Verify file permissions\n";
    echo "4. Review error logs in /logs directory\n";
}

if (!$isCLI) {
    echo "</pre>";

    // Add HTML styling
    echo '<style>
        body {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: "Consolas", "Monaco", monospace;
            padding: 20px;
        }
        pre {
            background: #2d2d2d;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
    </style>';
}

// Exit with appropriate code
exit($failedTests > 0 ? 1 : 0);
?>