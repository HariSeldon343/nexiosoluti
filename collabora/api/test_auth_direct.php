<?php
// Direct test of auth API
header('Content-Type: application/json');

// Include configuration
require_once __DIR__ . '/../config_v2.php';
require_once __DIR__ . '/../includes/auth_v2.php';

use Collabora\Auth\AuthenticationV2;

try {
    $auth = new AuthenticationV2();

    // Test login with hardcoded credentials
    $email = 'asamodeo@fortibyte.it';

    // First, set a known password hash
    $db = getDbConnection();

    // Use a simple password for testing
    $testPassword = 'password123';
    $hash = password_hash($testPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET password = :password WHERE email = :email");
    $stmt->execute([
        'password' => $hash,
        'email' => $email
    ]);

    // Now try to login
    $result = $auth->login($email, $testPassword);

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => $result['user'] ?? null,
        'tenants' => $result['tenants'] ?? [],
        'current_tenant_id' => $result['current_tenant_id'] ?? null
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}