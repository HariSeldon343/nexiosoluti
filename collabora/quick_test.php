<?php
/**
 * Quick test to verify admin login
 */

// Test credentials
$email = 'asamodeo@fortibyte.it';
$password = 'Ricord@1991';

echo "=== Quick Admin Login Test ===\n\n";

// Direct database connection
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=nexio_collabora_v2;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Database connected\n\n";

    // Fetch user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("✗ User not found!\n");
    }

    echo "✓ User found:\n";
    echo "  - Email: {$user['email']}\n";
    echo "  - Name: {$user['first_name']} {$user['last_name']}\n";
    echo "  - Role: {$user['role']}\n";
    echo "  - Status: {$user['status']}\n\n";

    // Verify password
    if (password_verify($password, $user['password'])) {
        echo "✅ PASSWORD CORRECT - Login should work!\n\n";

        // Get tenants
        $stmt = $pdo->prepare("
            SELECT t.*, uta.role as tenant_role
            FROM user_tenant_associations uta
            JOIN tenants t ON t.id = uta.tenant_id
            WHERE uta.user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "User's tenants:\n";
        foreach ($tenants as $tenant) {
            echo "  - {$tenant['name']} (Role: {$tenant['tenant_role']})\n";
        }

        echo "\n✅ ALL CHECKS PASSED!\n";
        echo "The user can login with:\n";
        echo "  Email: $email\n";
        echo "  Password: $password\n";
    } else {
        echo "✗ PASSWORD INCORRECT!\n";
    }

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}