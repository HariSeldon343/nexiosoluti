<?php
/**
 * User Session Status Endpoint
 * Returns current user information and session status
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Check if user is logged in
$isAuthenticated = isset($_SESSION['user_v2']) || isset($_SESSION['user']);

if (!$isAuthenticated) {
    // Not authenticated
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'Not authenticated'
    ]);
    exit;
}

// Get user data from session (try both v2 and legacy formats)
$user = $_SESSION['user_v2'] ?? $_SESSION['user'] ?? null;

if (!$user) {
    // Session exists but no user data
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'Invalid session state'
    ]);
    exit;
}

// Get tenant information
$tenantId = $_SESSION['tenant_id'] ?? null;
$tenantName = $_SESSION['tenant_name'] ?? null;

// Prepare response
$response = [
    'success' => true,
    'authenticated' => true,
    'user' => [
        'id' => $user['id'] ?? null,
        'email' => $user['email'] ?? null,
        'name' => $user['name'] ?? null,
        'role' => $user['role'] ?? 'standard_user'
    ],
    'tenant' => null,
    'session' => [
        'id' => session_id(),
        'created_at' => date('Y-m-d H:i:s', $_SESSION['created_at'] ?? time()),
        'last_activity' => date('Y-m-d H:i:s', $_SESSION['last_activity'] ?? time())
    ]
];

// Add tenant information if available
if ($tenantId) {
    $response['tenant'] = [
        'id' => $tenantId,
        'name' => $tenantName
    ];
}

// Update last activity
$_SESSION['last_activity'] = time();

// Send success response
http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT);