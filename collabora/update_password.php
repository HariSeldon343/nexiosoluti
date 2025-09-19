<?php
// Update password hash for test user
require_once 'config_v2.php';

$password = 'Ricord@1991';
$email = 'asamodeo@fortibyte.it';

// Generate proper password hash
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $db = getDbConnection();

    // Update password
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE email = :email");
    $result = $stmt->execute([
        'password' => $hash,
        'email' => $email
    ]);

    if ($result && $stmt->rowCount() > 0) {
        echo "Password updated successfully for $email\n";
        echo "Hash: $hash\n";
    } else {
        echo "User not found or password not updated\n";
    }

    // Verify the user exists
    $stmt = $db->prepare("SELECT id, email, role, is_system_admin FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "\nUser details:\n";
        echo "ID: " . $user['id'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "System Admin: " . ($user['is_system_admin'] ? 'Yes' : 'No') . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}