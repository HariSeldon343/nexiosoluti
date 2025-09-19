<?php
/**
 * Test completo dei percorsi API
 * Verifica che tutti gli endpoint API siano accessibili e funzionanti
 */

header('Content-Type: application/json');

// Configurazione base
$base_path = dirname($_SERVER['SCRIPT_NAME']);
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
            "://" . $_SERVER['HTTP_HOST'] . $base_path;

$test_results = [];
$all_passed = true;

// Test 1: Verifica esistenza file API
function test_api_files_exist() {
    $api_files = [
        'auth.php',
        'auth_v2.php',
        'auth_simple.php',
        'files.php',
        'folders.php',
        'users.php',
        'tenants.php',
        'webdav.php',
        'webhooks.php'
    ];

    $results = [];
    $api_dir = __DIR__ . '/api/';

    foreach ($api_files as $file) {
        $filepath = $api_dir . $file;
        $exists = file_exists($filepath);
        $results[] = [
            'file' => $file,
            'path' => $filepath,
            'exists' => $exists,
            'readable' => $exists ? is_readable($filepath) : false,
            'size' => $exists ? filesize($filepath) : 0
        ];
    }

    return [
        'test' => 'API Files Existence',
        'passed' => !in_array(false, array_column($results, 'exists')),
        'details' => $results
    ];
}

// Test 2: Verifica risoluzione URL
function test_url_resolution($base_url) {
    $test_urls = [
        'root' => $base_url . '/',
        'api_base' => $base_url . '/api/',
        'auth_v2' => $base_url . '/api/auth_v2.php',
        'auth_simple' => $base_url . '/api/auth_simple.php',
        'assets' => $base_url . '/assets/',
        'config' => $base_url . '/config_v2.php'
    ];

    $results = [];
    foreach ($test_urls as $key => $url) {
        // Parse URL per verificare struttura
        $parsed = parse_url($url);
        $results[$key] = [
            'url' => $url,
            'scheme' => $parsed['scheme'] ?? '',
            'host' => $parsed['host'] ?? '',
            'path' => $parsed['path'] ?? '',
            'valid' => filter_var($url, FILTER_VALIDATE_URL) !== false
        ];
    }

    return [
        'test' => 'URL Resolution',
        'passed' => !in_array(false, array_column($results, 'valid')),
        'base_url' => $base_url,
        'details' => $results
    ];
}

// Test 3: Verifica endpoint API raggiungibili
function test_api_endpoints($base_url) {
    $endpoints = [
        '/api/auth_v2.php' => ['method' => 'OPTIONS'],
        '/api/auth_simple.php' => ['method' => 'OPTIONS'],
        '/api/users.php' => ['method' => 'OPTIONS'],
        '/api/tenants.php' => ['method' => 'OPTIONS'],
        '/api/files.php' => ['method' => 'OPTIONS'],
        '/api/folders.php' => ['method' => 'OPTIONS']
    ];

    $results = [];

    foreach ($endpoints as $endpoint => $config) {
        $url = $base_url . $endpoint;

        // Inizializza cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $config['method']);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, $config['method'] === 'OPTIONS');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $results[] = [
            'endpoint' => $endpoint,
            'url' => $url,
            'method' => $config['method'],
            'http_code' => $http_code,
            'accessible' => $http_code > 0 && $http_code < 500,
            'error' => $error
        ];
    }

    return [
        'test' => 'API Endpoints Accessibility',
        'passed' => !in_array(false, array_column($results, 'accessible')),
        'details' => $results
    ];
}

// Test 4: Verifica configurazione
function test_configuration() {
    $config_files = [
        'config.php' => __DIR__ . '/config.php',
        'config_v2.php' => __DIR__ . '/config_v2.php',
        'config_v2_keys.php' => __DIR__ . '/config_v2_keys.php'
    ];

    $results = [];

    foreach ($config_files as $name => $path) {
        $exists = file_exists($path);
        $readable = $exists ? is_readable($path) : false;

        $config_data = [];
        if ($readable) {
            // Leggi alcune configurazioni chiave senza eseguire il file
            $content = file_get_contents($path);

            // Cerca pattern di configurazione
            if (preg_match('/DB_HOST.*=.*[\'"](.+?)[\'"]/i', $content, $matches)) {
                $config_data['db_host'] = $matches[1];
            }
            if (preg_match('/BASE_URL.*=.*[\'"](.+?)[\'"]/i', $content, $matches)) {
                $config_data['base_url'] = $matches[1];
            }
            if (preg_match('/API_URL.*=.*[\'"](.+?)[\'"]/i', $content, $matches)) {
                $config_data['api_url'] = $matches[1];
            }
        }

        $results[$name] = [
            'path' => $path,
            'exists' => $exists,
            'readable' => $readable,
            'size' => $exists ? filesize($path) : 0,
            'config_found' => $config_data
        ];
    }

    return [
        'test' => 'Configuration Files',
        'passed' => $results['config_v2.php']['exists'] && $results['config_v2.php']['readable'],
        'details' => $results
    ];
}

// Test 5: Verifica percorsi relativi
function test_relative_paths() {
    $script_path = $_SERVER['SCRIPT_NAME'];
    $request_uri = $_SERVER['REQUEST_URI'];
    $document_root = $_SERVER['DOCUMENT_ROOT'];
    $script_filename = $_SERVER['SCRIPT_FILENAME'];

    // Calcola percorso relativo
    $relative_path = str_replace($document_root, '', dirname($script_filename));
    $relative_path = str_replace('\\', '/', $relative_path);

    // Verifica se siamo in una sottocartella
    $depth = substr_count($relative_path, '/');
    $is_subfolder = $depth > 0;

    return [
        'test' => 'Path Resolution',
        'passed' => true,
        'details' => [
            'script_path' => $script_path,
            'request_uri' => $request_uri,
            'document_root' => $document_root,
            'script_filename' => $script_filename,
            'relative_path' => $relative_path,
            'depth' => $depth,
            'is_subfolder' => $is_subfolder,
            'detected_base' => dirname($script_path)
        ]
    ];
}

// Test 6: Verifica headers CORS
function test_cors_headers($base_url) {
    $test_url = $base_url . '/api/auth_v2.php';

    $ch = curl_init($test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers_str = substr($response, 0, $header_size);
    curl_close($ch);

    // Parse headers
    $headers = [];
    foreach (explode("\r\n", $headers_str) as $header) {
        if (strpos($header, ':') !== false) {
            list($key, $value) = explode(':', $header, 2);
            $headers[trim($key)] = trim($value);
        }
    }

    $cors_headers = [
        'Access-Control-Allow-Origin' => $headers['Access-Control-Allow-Origin'] ?? null,
        'Access-Control-Allow-Methods' => $headers['Access-Control-Allow-Methods'] ?? null,
        'Access-Control-Allow-Headers' => $headers['Access-Control-Allow-Headers'] ?? null
    ];

    return [
        'test' => 'CORS Headers',
        'passed' => isset($headers['Access-Control-Allow-Origin']),
        'details' => [
            'url_tested' => $test_url,
            'cors_headers' => $cors_headers,
            'all_headers' => $headers
        ]
    ];
}

// Test 7: Verifica database
function test_database_connection() {
    $config_file = __DIR__ . '/config_v2.php';

    if (!file_exists($config_file)) {
        return [
            'test' => 'Database Connection',
            'passed' => false,
            'details' => ['error' => 'config_v2.php not found']
        ];
    }

    // Includi configurazione
    ob_start();
    @include($config_file);
    ob_end_clean();

    $db_config = [
        'host' => defined('DB_HOST') ? DB_HOST : null,
        'name' => defined('DB_NAME') ? DB_NAME : null,
        'user' => defined('DB_USER') ? DB_USER : null,
        'configured' => defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')
    ];

    $connection_test = false;
    $error = null;

    if ($db_config['configured']) {
        try {
            $dsn = "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_config['user'], defined('DB_PASS') ? DB_PASS : '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Test query
            $stmt = $pdo->query("SELECT 1");
            $connection_test = true;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    return [
        'test' => 'Database Connection',
        'passed' => $connection_test,
        'details' => [
            'configured' => $db_config['configured'],
            'host' => $db_config['host'],
            'database' => $db_config['name'],
            'connection' => $connection_test,
            'error' => $error
        ]
    ];
}

// Esegui tutti i test
$test_results[] = test_api_files_exist();
$test_results[] = test_url_resolution($base_url);
$test_results[] = test_relative_paths();
$test_results[] = test_configuration();
$test_results[] = test_api_endpoints($base_url);
$test_results[] = test_cors_headers($base_url);
$test_results[] = test_database_connection();

// Calcola risultato complessivo
foreach ($test_results as $result) {
    if (!$result['passed']) {
        $all_passed = false;
        break;
    }
}

// Output finale
$output = [
    'timestamp' => date('Y-m-d H:i:s'),
    'environment' => [
        'php_version' => PHP_VERSION,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'base_url' => $base_url,
        'script_path' => $_SERVER['SCRIPT_NAME']
    ],
    'summary' => [
        'all_passed' => $all_passed,
        'total_tests' => count($test_results),
        'passed_tests' => count(array_filter($test_results, function($r) { return $r['passed']; })),
        'failed_tests' => count(array_filter($test_results, function($r) { return !$r['passed']; }))
    ],
    'tests' => $test_results
];

// Pretty print JSON
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);