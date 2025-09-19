<?php
// Generate password hash
$password = 'Ricord@1991';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: $password\n";
echo "Hash: $hash\n";
echo "\nSQL to update:\n";
echo "UPDATE users SET password = '$hash' WHERE email = 'asamodeo@fortibyte.it';\n";