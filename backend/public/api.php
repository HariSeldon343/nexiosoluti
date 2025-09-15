<?php
/**
 * API endpoint standalone per NexioSolution
 * Gestisce autenticazione senza dipendenze Laravel
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Gestione preflight OPTIONS per CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Parse del path richiesto
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/api.php', '', $path);
$path = str_replace('/api', '', $path);

// Routing semplice
switch ($path) {
    case '/test':
        echo json_encode([
            'status' => 'API is working',
            'time' => date('Y-m-d H:i:s')
        ]);
        break;

    case '/login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        // Verifica credenziali (mock)
        if ($email === 'admin@nexiosolution.com' && $password === 'password123') {
            echo json_encode([
                'success' => true,
                'token' => 'mock-jwt-token-' . uniqid(),
                'user' => [
                    'id' => 1,
                    'name' => 'Admin',
                    'email' => 'admin@nexiosolution.com',
                    'role' => 'admin'
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Credenziali non valide'
            ]);
        }
        break;

    case '/user':
        // Mock user data
        echo json_encode([
            'id' => 1,
            'name' => 'Admin',
            'email' => 'admin@nexiosolution.com',
            'role' => 'admin'
        ]);
        break;

    case '/logout':
        echo json_encode([
            'success' => true,
            'message' => 'Logout effettuato'
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
}
?>