<?php
declare(strict_types=1);

/**
 * API endpoints per autenticazione
 * Login, logout, verifica sessione, gestione utenti
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Headers CORS e JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Gestione OPTIONS per CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Inizializza sessione
Auth::initSession();

// Router semplice
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            handleLogin();
            break;

        case 'logout':
            handleLogout();
            break;

        case 'check':
            handleCheckSession();
            break;

        case 'register':
            handleRegister();
            break;

        case 'change_password':
            handleChangePassword();
            break;

        case 'get_user':
            handleGetUser();
            break;

        case 'update_user':
            handleUpdateUser();
            break;

        case 'csrf_token':
            handleGetCsrfToken();
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Login utente
 */
function handleLogin(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $tenantCode = $data['tenant_code'] ?? $data['tenant'] ?? '';

    if (empty($username) || empty($password) || empty($tenantCode)) {
        throw new Exception('Missing required fields');
    }

    if (Auth::login($username, $password, $tenantCode)) {
        $user = Auth::getCurrentUser();
        $tenant = Auth::getCurrentTenant();

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ],
            'tenant' => [
                'id' => $tenant['id'],
                'code' => $tenant['code'],
                'name' => $tenant['name']
            ],
            'csrf_token' => Auth::generateCsrfToken()
        ]);
    } else {
        throw new Exception('Invalid credentials');
    }
}

/**
 * Logout utente
 */
function handleLogout(): void {
    Auth::logout();

    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

/**
 * Verifica sessione
 */
function handleCheckSession(): void {
    if (!Auth::isLoggedIn()) {
        echo json_encode([
            'success' => false,
            'authenticated' => false
        ]);
        return;
    }

    $user = Auth::getCurrentUser();
    $tenant = Auth::getCurrentTenant();

    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ],
        'tenant' => [
            'id' => $tenant['id'],
            'code' => $tenant['code'],
            'name' => $tenant['name']
        ]
    ]);
}

/**
 * Registra nuovo utente
 */
function handleRegister(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // Solo admin può registrare nuovi utenti
    Auth::requireAuth();
    Auth::requirePermission('user.create');

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    // Validazione
    $required = ['username', 'email', 'password', 'full_name'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field {$field} is required");
        }
    }

    // Validazione email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Validazione password
    if (strlen($data['password']) < 8) {
        throw new Exception('Password must be at least 8 characters');
    }

    // Aggiungi tenant_id corrente
    $data['tenant_id'] = Auth::getTenantId();

    // Crea utente
    $userId = Auth::createUser($data);

    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'message' => 'User created successfully'
    ]);
}

/**
 * Cambia password
 */
function handleChangePassword(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requireAuth();
    Auth::requireCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $oldPassword = $data['old_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';

    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        throw new Exception('All fields are required');
    }

    if ($newPassword !== $confirmPassword) {
        throw new Exception('New passwords do not match');
    }

    if (strlen($newPassword) < 8) {
        throw new Exception('Password must be at least 8 characters');
    }

    $userId = Auth::getUserId();
    if (!Auth::updatePassword($userId, $oldPassword, $newPassword)) {
        throw new Exception('Current password is incorrect');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
}

/**
 * Ottiene info utente
 */
function handleGetUser(): void {
    Auth::requireAuth();

    $userId = (int)($_GET['id'] ?? Auth::getUserId());

    // Se non è il proprio profilo, richiede permessi
    if ($userId !== Auth::getUserId()) {
        Auth::requirePermission('user.view');
    }

    $user = Database::selectOne(
        "SELECT id, username, email, full_name, role, status, last_login, created_at
         FROM users
         WHERE id = :id AND tenant_id = :tenant_id",
        ['id' => $userId, 'tenant_id' => Auth::getTenantId()]
    );

    if (!$user) {
        throw new Exception('User not found');
    }

    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
}

/**
 * Aggiorna info utente
 */
function handleUpdateUser(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requireAuth();
    Auth::requireCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $userId = (int)($data['id'] ?? Auth::getUserId());

    // Se non è il proprio profilo, richiede permessi admin
    if ($userId !== Auth::getUserId()) {
        Auth::requirePermission('user.edit');
    }

    // Campi aggiornabili
    $allowedFields = ['full_name', 'email'];
    $updateData = [];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }

    // Solo admin può cambiare ruolo e status
    if (Auth::checkPermission('user.admin')) {
        if (isset($data['role'])) {
            $updateData['role'] = $data['role'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
    }

    if (empty($updateData)) {
        throw new Exception('No fields to update');
    }

    // Validazione email se presente
    if (isset($updateData['email']) && !filter_var($updateData['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Verifica unicità email
    if (isset($updateData['email'])) {
        $existing = Database::selectOne(
            "SELECT id FROM users
             WHERE email = :email AND tenant_id = :tenant_id AND id != :id",
            [
                'email' => $updateData['email'],
                'tenant_id' => Auth::getTenantId(),
                'id' => $userId
            ]
        );

        if ($existing) {
            throw new Exception('Email already in use');
        }
    }

    Database::update('users', $updateData, ['id' => $userId]);

    // Log attività
    Auth::logActivity('user_update', 'user', $userId, $updateData);

    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
}

/**
 * Ottiene token CSRF
 */
function handleGetCsrfToken(): void {
    echo json_encode([
        'success' => true,
        'csrf_token' => Auth::generateCsrfToken()
    ]);
}