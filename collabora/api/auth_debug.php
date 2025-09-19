<?php
/**
 * Authentication Debug Endpoint
 * Provides diagnostic information and contract documentation
 * WARNING: This should be disabled in production!
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include configuration
require_once __DIR__ . '/../config_v2.php';

// Check if debug mode is enabled
if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Debug mode is disabled'
    ]);
    exit;
}

// Test database connection
function testDatabaseConnection() {
    try {
        if (file_exists(__DIR__ . '/../includes/db.php')) {
            require_once __DIR__ . '/../includes/db.php';
            if (function_exists('getDbConnection')) {
                $db = getDbConnection();
                $db->query('SELECT 1');
                return ['success' => true, 'message' => 'Database connection successful'];
            }
        }

        // Fallback connection test
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->query('SELECT 1');
        return ['success' => true, 'message' => 'Database connection successful (fallback)'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
    }
}

// Get available users (email only, no passwords)
function getAvailableUsers() {
    try {
        if (file_exists(__DIR__ . '/../includes/db.php')) {
            require_once __DIR__ . '/../includes/db.php';
            $db = getDbConnection();
        } else {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $db = new PDO($dsn, DB_USER, DB_PASS);
        }

        $stmt = $db->query("
            SELECT email, first_name, last_name, role, status
            FROM users
            WHERE (deleted_at IS NULL OR deleted_at = '')
            ORDER BY role, email
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'users' => $users];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to fetch users: ' . $e->getMessage()];
    }
}

// Check table structure
function checkTableStructure() {
    try {
        if (file_exists(__DIR__ . '/../includes/db.php')) {
            require_once __DIR__ . '/../includes/db.php';
            $db = getDbConnection();
        } else {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $db = new PDO($dsn, DB_USER, DB_PASS);
        }

        $tables = [];

        // Check users table
        $stmt = $db->query("SHOW COLUMNS FROM users");
        $tables['users'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Check tenants table
        try {
            $stmt = $db->query("SHOW COLUMNS FROM tenants");
            $tables['tenants'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $tables['tenants'] = 'Table does not exist';
        }

        // Check association table
        try {
            $stmt = $db->query("SHOW COLUMNS FROM user_tenant_associations");
            $tables['user_tenant_associations'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            // Try alternate name
            try {
                $stmt = $db->query("SHOW COLUMNS FROM user_tenants");
                $tables['user_tenants'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e2) {
                $tables['user_tenant_associations'] = 'Table does not exist';
            }
        }

        return ['success' => true, 'tables' => $tables];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to check table structure: ' . $e->getMessage()];
    }
}

// Main debug output
$debug = [
    'api_info' => [
        'endpoint' => 'auth_simple.php',
        'version' => API_VERSION ?? 'v2',
        'base_url' => BASE_URL ?? 'Not configured',
        'debug_mode' => DEBUG_MODE ?? false,
        'php_version' => PHP_VERSION
    ],

    'authentication_contract' => [
        'endpoint' => '/api/auth_simple.php',
        'supported_actions' => ['login', 'logout', 'switch_tenant', 'check', 'test'],
        'supported_content_types' => [
            'application/json',
            'application/x-www-form-urlencoded'
        ],

        'login_request' => [
            'required_fields' => [
                'action' => 'login',
                'email' => 'user@example.com',
                'password' => 'user_password'
            ],
            'response_codes' => [
                '200' => 'Successful login',
                '400' => 'Bad request (missing fields, malformed JSON)',
                '401' => 'Invalid credentials',
                '403' => 'Account inactive',
                '500' => 'Server error'
            ]
        ],

        'example_requests' => [
            'json' => [
                'method' => 'POST',
                'url' => BASE_URL . '/api/auth_simple.php',
                'headers' => ['Content-Type: application/json'],
                'body' => json_encode([
                    'action' => 'login',
                    'email' => 'asamodeo@fortibyte.it',
                    'password' => 'Ricord@1991'
                ], JSON_PRETTY_PRINT)
            ],
            'form' => [
                'method' => 'POST',
                'url' => BASE_URL . '/api/auth_simple.php',
                'headers' => ['Content-Type: application/x-www-form-urlencoded'],
                'body' => 'action=login&email=asamodeo@fortibyte.it&password=Ricord@1991'
            ],
            'curl_json' => "curl -X POST '" . BASE_URL . "/api/auth_simple.php' \\
  -H 'Content-Type: application/json' \\
  -d '{\"action\":\"login\",\"email\":\"asamodeo@fortibyte.it\",\"password\":\"Ricord@1991\"}'",
            'curl_form' => "curl -X POST '" . BASE_URL . "/api/auth_simple.php' \\
  -H 'Content-Type: application/x-www-form-urlencoded' \\
  -d 'action=login&email=asamodeo@fortibyte.it&password=Ricord@1991'"
        ],

        'response_formats' => [
            'success_200' => [
                'success' => true,
                'message' => 'Login effettuato con successo',
                'user' => [
                    'id' => 1,
                    'email' => 'user@example.com',
                    'name' => 'User Name',
                    'role' => 'admin|special_user|standard_user',
                    'is_admin' => true
                ],
                'tenants' => [
                    ['id' => 1, 'code' => 'DEFAULT', 'name' => 'Default Tenant']
                ],
                'current_tenant_id' => 1,
                'session_id' => 'session_id_string',
                'token' => 'session_id_string'
            ],
            'error_400' => [
                'success' => false,
                'error' => [
                    'code' => 'missing_fields|invalid_json|invalid_action',
                    'message' => 'Human readable error message',
                    'fields' => ['list', 'of', 'missing', 'fields']
                ]
            ],
            'error_401' => [
                'success' => false,
                'error' => [
                    'code' => 'invalid_credentials',
                    'message' => 'Email o password non corretti',
                    'fields' => []
                ]
            ]
        ]
    ],

    'system_checks' => [
        'database' => testDatabaseConnection(),
        'table_structure' => checkTableStructure(),
        'available_users' => getAvailableUsers(),
        'session_status' => [
            'session_id' => session_id() ?: 'Not started',
            'session_name' => ini_get('session.name'),
            'session_path' => session_save_path(),
            'session_status' => session_status()
        ],
        'php_extensions' => [
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl')
        ]
    ],

    'test_credentials' => [
        'note' => 'Default admin credentials for testing',
        'email' => DEFAULT_ADMIN_EMAIL ?? 'asamodeo@fortibyte.it',
        'password' => 'Ricord@1991',
        'warning' => 'Change these credentials in production!'
    ]
];

// Pretty print JSON
echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);