<?php
/**
 * Test API endpoint per verificare che il backend risponda correttamente
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestione preflight OPTIONS per CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Risposta di test
$response = [
    'status' => 'ok',
    'message' => 'Backend Laravel attivo e funzionante',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'laravel_available' => file_exists('../vendor/autoload.php'),
    'env_configured' => file_exists('../.env'),
    'database_configured' => false,
    'test_credentials' => [
        'email' => 'admin@nexiosolution.com',
        'password' => 'password123',
        'note' => 'Usa queste credenziali per il login'
    ],
    'api_endpoints' => [
        'login' => '/api/login',
        'user' => '/api/user',
        'dashboard' => '/api/dashboard/stats'
    ]
];

// Verifica connessione database se possibile
if (file_exists('../.env')) {
    $env = parse_ini_file('../.env');
    if (isset($env['DB_CONNECTION'])) {
        $response['database_configured'] = true;
        $response['database_type'] = $env['DB_CONNECTION'];
    }
}

// Output JSON
echo json_encode($response, JSON_PRETTY_PRINT);
?>