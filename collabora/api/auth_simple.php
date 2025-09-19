<?php
/**
 * Simple Authentication API Endpoint
 * Supports both JSON and form-encoded data
 *
 * Status Codes:
 * - 200: Successful operation
 * - 400: Bad Request (malformed JSON, missing required fields)
 * - 401: Unauthorized (invalid credentials)
 * - 500: Internal Server Error
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include Simple Auth class
require_once __DIR__ . '/../includes/SimpleAuth.php';

// Safe logging function
function safeLog($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . ' [auth_simple] ' . $message;

    if ($data !== null) {
        // Sanitize sensitive data
        if (is_array($data)) {
            $safe_data = $data;
            // Mask password fields
            if (isset($safe_data['password'])) {
                $safe_data['password'] = '***MASKED***';
            }
            if (isset($safe_data['new_password'])) {
                $safe_data['new_password'] = '***MASKED***';
            }
            if (isset($safe_data['confirm_password'])) {
                $safe_data['confirm_password'] = '***MASKED***';
            }
            // Mask token if present
            if (isset($safe_data['token']) && strlen($safe_data['token']) > 10) {
                $safe_data['token'] = substr($safe_data['token'], 0, 6) . '...' . substr($safe_data['token'], -4);
            }
            $logEntry .= ' | Data: ' . json_encode($safe_data);
        } else {
            $logEntry .= ' | Data: ' . $data;
        }
    }

    error_log($logEntry);
}

try {
    // Get input data - support both JSON and form-encoded
    $data = [];
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $inputFormat = 'unknown';
    $rawInput = file_get_contents('php://input');

    // Log request details safely
    safeLog('Request', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $contentType,
        'body_length' => strlen($rawInput),
        'has_post_data' => !empty($_POST)
    ]);

    // Parse based on Content-Type
    if (stripos($contentType, 'application/json') !== false) {
        // Handle JSON input
        $inputFormat = 'json';

        if (empty($rawInput)) {
            // Missing request body
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'empty_body',
                    'message' => 'Request body is empty',
                    'fields' => []
                ]
            ]);
            exit;
        }

        $data = json_decode($rawInput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Malformed JSON
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'invalid_json',
                    'message' => 'Invalid JSON: ' . json_last_error_msg(),
                    'fields' => []
                ]
            ]);
            exit;
        }
    } elseif (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
        // Handle form-encoded data
        $inputFormat = 'form';
        $data = $_POST;
    } else {
        // Try to detect format automatically
        if (!empty($rawInput)) {
            // Try JSON first
            $data = json_decode($rawInput, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $inputFormat = 'json_auto';
            } else {
                // Try query string format
                parse_str($rawInput, $data);
                if (!empty($data)) {
                    $inputFormat = 'form_auto';
                }
            }
        }
    }

    // If still no data, check $_REQUEST as fallback
    if (empty($data) && !empty($_REQUEST)) {
        $data = $_REQUEST;
        $inputFormat = 'request_fallback';
    }

    // Log parsed data (sanitized)
    safeLog('Parsed input', [
        'format' => $inputFormat,
        'fields' => array_keys($data),
        'data' => $data
    ]);

    // Check if we have any data
    if (empty($data)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'no_data',
                'message' => 'No input data received',
                'fields' => ['action']
            ]
        ]);
        exit;
    }

    $auth = new SimpleAuth();
    $action = $data['action'] ?? '';

    // Check if action is provided
    if (empty($action)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'missing_field',
                'message' => 'Action field is required',
                'fields' => ['action']
            ]
        ]);
        exit;
    }

    safeLog('Action requested: ' . $action);

    switch ($action) {
        case 'login':
            // Validate required fields
            $missingFields = [];
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';

            if (empty($email)) {
                $missingFields[] = 'email';
            }
            if (empty($password)) {
                $missingFields[] = 'password';
            }

            if (!empty($missingFields)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'missing_fields',
                        'message' => 'Required fields are missing',
                        'fields' => $missingFields
                    ]
                ]);
                exit;
            }

            safeLog('Login attempt', ['email' => $email]);

            try {
                $result = $auth->login($email, $password);

                // Success - return 200
                http_response_code(200);

                // Determine suggested redirect based on user role
                $suggestedRedirect = null;
                if ($result['user']['role'] === 'admin') {
                    $suggestedRedirect = '/Nexiosolution/collabora/admin/index.php';
                } elseif ($result['user']['role'] === 'special_user') {
                    $suggestedRedirect = '/Nexiosolution/collabora/home_v2.php';
                } else {
                    $suggestedRedirect = '/Nexiosolution/collabora/home_v2.php';
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Login effettuato con successo',
                    'user' => $result['user'],
                    'tenants' => $result['tenants'],
                    'current_tenant_id' => $result['current_tenant_id'],
                    'session_id' => $result['session_id'],
                    'token' => $result['session_id'], // For compatibility
                    'redirect' => $suggestedRedirect  // Optional redirect suggestion
                ]);

                safeLog('Login successful', ['user_id' => $result['user']['id'], 'email' => $email]);
            } catch (Exception $loginError) {
                // Check error type
                $errorMessage = $loginError->getMessage();

                if (strpos($errorMessage, 'Email e password sono obbligatori') !== false) {
                    // This shouldn't happen as we check above, but just in case
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => [
                            'code' => 'missing_fields',
                            'message' => $errorMessage,
                            'fields' => ['email', 'password']
                        ]
                    ]);
                } elseif (strpos($errorMessage, 'Database') !== false) {
                    // Database error - 500
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => [
                            'code' => 'database_error',
                            'message' => 'Database connection error',
                            'fields' => []
                        ]
                    ]);
                    safeLog('Database error during login', ['error' => $errorMessage]);
                } else {
                    // Authentication failed - 401
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => [
                            'code' => 'invalid_credentials',
                            'message' => 'Email o password non corretti',
                            'fields' => []
                        ]
                    ]);
                    safeLog('Login failed', ['email' => $email, 'reason' => $errorMessage]);
                }
                exit;
            }
            break;

        case 'logout':
            $result = $auth->logout();
            echo json_encode($result);
            break;

        case 'switch_tenant':
            $tenantId = $data['tenant_id'] ?? 0;
            $auth->switchTenant($tenantId);
            echo json_encode([
                'success' => true,
                'message' => 'Tenant cambiato con successo',
                'current_tenant_id' => $tenantId
            ]);
            break;

        case 'check':
            echo json_encode([
                'success' => true,
                'authenticated' => $auth->isAuthenticated(),
                'user' => $auth->getCurrentUser()
            ]);
            break;

        case 'test':
            // Test endpoint for API configuration verification
            echo json_encode([
                'success' => true,
                'message' => 'API endpoint is working correctly',
                'endpoint' => 'auth_simple.php',
                'method' => $_SERVER['REQUEST_METHOD'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        default:
            // Invalid action - 400
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'invalid_action',
                    'message' => 'Invalid action: "' . $action . '". Valid actions are: login, logout, switch_tenant, check, test',
                    'fields' => ['action']
                ]
            ]);
            exit;
    }

} catch (Exception $e) {
    // Log the error
    safeLog('Unexpected error', ['message' => $e->getMessage()]);

    // General server error - 500
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'server_error',
            'message' => 'Internal server error occurred',
            'fields' => []
        ],
        'debug' => (defined('DEBUG_MODE') && DEBUG_MODE) ? [
            'actual_error' => $e->getMessage(),
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
        ] : null
    ]);
}