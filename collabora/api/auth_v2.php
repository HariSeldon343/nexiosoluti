<?php declare(strict_types=1);

/**
 * Authentication API v2 Endpoints
 * Gestione autenticazione e sessioni
 *
 * @author Nexiosolution
 * @version 2.0.0
 * @since 2025-01-17
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS headers
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
    exit(0);
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

// Include configurazione e connessione database
require_once __DIR__ . '/../config_v2.php';

// Check if autoloader exists, otherwise include files directly
if (file_exists(__DIR__ . '/../includes/autoload.php')) {
    require_once __DIR__ . '/../includes/autoload.php';
} else {
    require_once __DIR__ . '/../includes/auth_v2.php';
    if (file_exists(__DIR__ . '/../includes/TenantManager_v2.php')) {
        require_once __DIR__ . '/../includes/TenantManager_v2.php';
    }
}

use Collabora\Auth\AuthenticationV2;

// Check if TenantManagerV2 exists, otherwise create a stub
if (!class_exists('Collabora\Tenants\TenantManagerV2')) {
    // Create stub TenantManagerV2 class if it doesn't exist
    namespace Collabora\Tenants {
        class TenantManagerV2 {
            private $db;

            public function __construct() {
                $this->db = \getDbConnection();
            }

            public function getUserAvailableTenants() {
                if (!isset($_SESSION['user_id'])) {
                    return [];
                }

                try {
                    $stmt = $this->db->prepare("
                        SELECT DISTINCT t.*
                        FROM tenants t
                        LEFT JOIN user_tenant_associations uta ON t.id = uta.tenant_id
                        WHERE uta.user_id = :user_id OR :is_admin = 1
                        ORDER BY t.name
                    ");

                    $stmt->execute([
                        'user_id' => $_SESSION['user_id'],
                        'is_admin' => $_SESSION['role'] === 'admin' ? 1 : 0
                    ]);

                    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    error_log("Error getting user tenants: " . $e->getMessage());
                    return [];
                }
            }

            public function getTenantById($id) {
                try {
                    $stmt = $this->db->prepare("SELECT * FROM tenants WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    return $stmt->fetch(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    error_log("Error getting tenant: " . $e->getMessage());
                    return null;
                }
            }
        }
    }
}

/**
 * Classe per gestire le richieste API di autenticazione
 */
class AuthAPIV2 {
    private AuthenticationV2 $auth;
    private $tenantManager;  // Remove type hint for flexibility
    private array $request;
    private string $method;
    private array $urlParts;

    public function __construct() {
        $this->auth = new AuthenticationV2();
        $this->tenantManager = new \Collabora\Tenants\TenantManagerV2();
        $this->method = $_SERVER['REQUEST_METHOD'];

        // Parse URL - simplified to avoid path issues
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        // Extract endpoint from the path (everything after auth_v2.php)
        $scriptName = basename($_SERVER['SCRIPT_NAME']);
        $endpoint = '';
        if (strpos($path, $scriptName) !== false) {
            $parts = explode($scriptName, $path);
            if (isset($parts[1])) {
                $endpoint = trim($parts[1], '/');
            }
        }
        $this->urlParts = array_filter(explode('/', $endpoint));

        // Parse request body
        $this->request = [];
        if (in_array($this->method, ['POST', 'PUT', 'PATCH'])) {
            $input = file_get_contents('php://input');
            if ($input) {
                $this->request = json_decode($input, true) ?? [];
            }
        }
    }

    /**
     * Processa la richiesta
     */
    public function handle(): void {
        try {
            // Supporta sia action nel body che endpoint nell'URL
            $endpoint = $this->urlParts[0] ?? '';

            // Se non c'è endpoint nell'URL, prova a prendere action dal body JSON
            if (empty($endpoint) && isset($this->request['action'])) {
                $endpoint = $this->request['action'];
            }

            switch ($this->method) {
                case 'POST':
                    $this->handlePost($endpoint);
                    break;

                case 'GET':
                    $this->handleGet($endpoint);
                    break;

                case 'PUT':
                    $this->handlePut($endpoint);
                    break;

                case 'DELETE':
                    $this->handleDelete($endpoint);
                    break;

                default:
                    $this->sendError('Metodo non supportato', 405);
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Gestisce richieste POST
     */
    private function handlePost(string $endpoint): void {
        switch ($endpoint) {
            case 'login':
                $this->login();
                break;

            case 'logout':
                $this->logout();
                break;

            case 'switch-tenant':
            case 'switch_tenant':  // Support both formats
                $this->switchTenant();
                break;

            case 'validate-token':
                $this->validateToken();
                break;

            case 'refresh-session':
                $this->refreshSession();
                break;

            case 'test':
                // Test endpoint for API configuration verification
                $this->sendSuccess([
                    'message' => 'API endpoint is working correctly',
                    'endpoint' => 'auth_v2.php',
                    'method' => $this->method,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'version' => '2.0.0'
                ]);
                break;

            default:
                $this->sendError('Endpoint non trovato', 404);
        }
    }

    /**
     * Gestisce richieste GET
     */
    private function handleGet(string $endpoint): void {
        switch ($endpoint) {
            case 'me':
            case 'current-user':
                $this->getCurrentUser();
                break;

            case 'tenants':
            case 'my-tenants':
                $this->getUserTenants();
                break;

            case 'current-tenant':
                $this->getCurrentTenant();
                break;

            case 'permissions':
                $this->getUserPermissions();
                break;

            case 'session-status':
                $this->getSessionStatus();
                break;

            case 'csrf-token':
                $this->getCSRFToken();
                break;

            default:
                $this->sendError('Endpoint non trovato', 404);
        }
    }

    /**
     * Gestisce richieste PUT
     */
    private function handlePut(string $endpoint): void {
        switch ($endpoint) {
            case 'password':
                $this->changePassword();
                break;

            case 'profile':
                $this->updateProfile();
                break;

            default:
                $this->sendError('Endpoint non trovato', 404);
        }
    }

    /**
     * Gestisce richieste DELETE
     */
    private function handleDelete(string $endpoint): void {
        switch ($endpoint) {
            case 'sessions':
                $this->terminateAllSessions();
                break;

            default:
                $this->sendError('Endpoint non trovato', 404);
        }
    }

    /**
     * Login utente
     */
    private function login(): void {
        // Valida input
        if (empty($this->request['email']) || empty($this->request['password'])) {
            $this->sendError('Email e password sono obbligatori', 400);
            return;
        }

        try {
            // Esegui login
            $result = $this->auth->login(
                $this->request['email'],
                $this->request['password']
            );

            // Imposta cookie di sessione sicuro
            $this->setSecureSessionCookie();

            $response = [
                'message' => 'Login effettuato con successo',
                'user' => $result['user'],
                'tenants' => $result['tenants'] ?? [],
                'current_tenant_id' => $result['current_tenant_id'] ?? null,
                'session_id' => $result['session_id'] ?? session_id(),
                'csrf_token' => $this->auth->generateCSRFToken()
            ];

            // Send JSON response with proper structure
            $this->sendSuccess($response);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 401);
        }
    }

    /**
     * Logout utente
     */
    private function logout(): void {
        try {
            $this->auth->logout();

            // Rimuovi cookie di sessione
            $this->removeSessionCookie();

            $this->sendSuccess(['message' => 'Logout effettuato con successo']);
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Cambia tenant attivo
     */
    private function switchTenant(): void {
        // Verifica autenticazione
        if (!$this->auth->isAuthenticated()) {
            $this->sendError('Non autenticato', 401);
            return;
        }

        // Valida CSRF token
        if (!$this->validateCSRF()) {
            $this->sendError('Token CSRF non valido', 403);
            return;
        }

        // Valida input
        if (empty($this->request['tenant_id'])) {
            $this->sendError('ID tenant obbligatorio', 400);
            return;
        }

        try {
            $tenantId = (int)$this->request['tenant_id'];
            $success = $this->auth->switchTenant($tenantId);

            if ($success) {
                // Ottieni info del nuovo tenant
                $tenant = $this->tenantManager->getTenantById($tenantId);

                $this->sendSuccess([
                    'message' => 'Tenant cambiato con successo',
                    'tenant' => $tenant,
                    'current_tenant_id' => $tenantId
                ]);
            } else {
                $this->sendError('Impossibile cambiare tenant', 400);
            }

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Ottiene l'utente corrente
     */
    private function getCurrentUser(): void {
        if (!$this->auth->isAuthenticated()) {
            $this->sendError('Non autenticato', 401);
            return;
        }

        $user = $this->auth->getCurrentUser();
        if ($user) {
            // Aggiungi info aggiuntive
            $user['is_admin'] = $this->auth->isAdmin();
            $user['can_switch_tenant'] = $this->auth->canSwitchTenant($user);
            $user['current_tenant_id'] = $this->auth->getCurrentTenantId();

            $this->sendSuccess($user);
        } else {
            $this->sendError('Utente non trovato', 404);
        }
    }

    /**
     * Ottiene i tenant disponibili per l'utente
     */
    private function getUserTenants(): void {
        if (!$this->auth->isAuthenticated()) {
            $this->sendError('Non autenticato', 401);
            return;
        }

        try {
            $tenants = $this->tenantManager->getUserAvailableTenants();
            $this->sendSuccess([
                'tenants' => $tenants,
                'current_tenant_id' => $this->auth->getCurrentTenantId()
            ]);
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Ottiene il tenant corrente
     */
    private function getCurrentTenant(): void {
        if (!$this->auth->isAuthenticated()) {
            $this->sendError('Non autenticato', 401);
            return;
        }

        $tenantId = $this->auth->getCurrentTenantId();
        if (!$tenantId) {
            $this->sendSuccess(['tenant' => null]);
            return;
        }

        try {
            $tenant = $this->tenantManager->getTenantById($tenantId);
            $this->sendSuccess(['tenant' => $tenant]);
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Ottiene i permessi dell'utente
     */
    private function getUserPermissions(): void {
        if (!$this->auth->isAuthenticated()) {
            $this->sendError('Non autenticato', 401);
            return;
        }

        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->sendError('Utente non trovato', 404);
            return;
        }

        // Lista permessi comuni da verificare
        $commonPermissions = [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'tenants.view', 'tenants.create', 'tenants.edit', 'tenants.delete', 'tenants.switch',
            'files.view', 'files.create', 'files.edit', 'files.delete',
            'settings.view', 'settings.edit',
            'logs.view', 'system.admin'
        ];

        $permissions = [];
        foreach ($commonPermissions as $perm) {
            $permissions[$perm] = $this->auth->hasPermission($perm);
        }

        $this->sendSuccess([
            'role' => $user['role'],
            'is_admin' => $user['is_system_admin'] ?? false,
            'permissions' => $permissions
        ]);
    }

    /**
     * Ottiene lo stato della sessione
     */
    private function getSessionStatus(): void {
        $isAuthenticated = $this->auth->isAuthenticated();

        $response = [
            'authenticated' => $isAuthenticated,
            'session_id' => session_id()
        ];

        if ($isAuthenticated) {
            $user = $this->auth->getCurrentUser();
            $response['user_id'] = $user['id'] ?? null;
            $response['user_email'] = $user['email'] ?? null;
            $response['current_tenant_id'] = $this->auth->getCurrentTenantId();
            $response['session_age'] = time() - ($_SESSION['login_time'] ?? time());
        }

        $this->sendSuccess($response);
    }

    /**
     * Genera un token CSRF
     */
    private function getCSRFToken(): void {
        if (!$this->auth->isAuthenticated()) {
            $this->sendError('Non autenticato', 401);
            return;
        }

        $token = $this->auth->generateCSRFToken();
        $this->sendSuccess(['csrf_token' => $token]);
    }

    /**
     * Valida token (per compatibilità)
     */
    private function validateToken(): void {
        $isValid = $this->auth->isAuthenticated();

        if ($isValid) {
            $user = $this->auth->getCurrentUser();
            $this->sendSuccess([
                'valid' => true,
                'user' => $user,
                'current_tenant_id' => $this->auth->getCurrentTenantId()
            ]);
        } else {
            $this->sendSuccess(['valid' => false]);
        }
    }

    /**
     * Rinnova la sessione
     */
    private function refreshSession(): void {
        if (!$this->auth->isAuthenticated()) {
            $this->sendError('Non autenticato', 401);
            return;
        }

        // Rigenera ID sessione per sicurezza
        session_regenerate_id(true);
        $_SESSION['last_regenerate'] = time();

        $this->sendSuccess([
            'message' => 'Sessione rinnovata',
            'session_id' => session_id(),
            'csrf_token' => $this->auth->generateCSRFToken()
        ]);
    }

    /**
     * Cambia password utente
     */
    private function changePassword(): void {
        if (!$this->auth->isAuthenticated()) {
            $this->sendError('Non autenticato', 401);
            return;
        }

        // Valida CSRF
        if (!$this->validateCSRF()) {
            $this->sendError('Token CSRF non valido', 403);
            return;
        }

        // Valida input
        if (empty($this->request['current_password']) || empty($this->request['new_password'])) {
            $this->sendError('Password corrente e nuova sono obbligatorie', 400);
            return;
        }

        try {
            $user = $this->auth->getCurrentUser();

            // Verifica password corrente
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
            $stmt->execute(['id' => $user['id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!password_verify($this->request['current_password'], $userData['password'])) {
                $this->sendError('Password corrente non corretta', 400);
                return;
            }

            // Aggiorna password
            $newHash = password_hash($this->request['new_password'], PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);

            $stmt = $db->prepare("
                UPDATE users
                SET password = :password,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'password' => $newHash,
                'id' => $user['id']
            ]);

            $this->sendSuccess(['message' => 'Password aggiornata con successo']);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Aggiorna profilo utente
     */
    private function updateProfile(): void {
        if (!$this->auth->isAuthenticated()) {
            $this->sendError('Non autenticato', 401);
            return;
        }

        // Valida CSRF
        if (!$this->validateCSRF()) {
            $this->sendError('Token CSRF non valido', 403);
            return;
        }

        try {
            $user = $this->auth->getCurrentUser();
            $db = getDbConnection();

            // Campi aggiornabili
            $allowedFields = ['first_name', 'last_name', 'phone', 'timezone', 'language'];
            $updates = [];
            $params = ['id' => $user['id']];

            foreach ($allowedFields as $field) {
                if (isset($this->request[$field])) {
                    $updates[] = "$field = :$field";
                    $params[$field] = $this->request[$field];
                }
            }

            if (empty($updates)) {
                $this->sendError('Nessun campo da aggiornare', 400);
                return;
            }

            $updates[] = "updated_at = NOW()";

            $stmt = $db->prepare("
                UPDATE users
                SET " . implode(', ', $updates) . "
                WHERE id = :id
            ");

            $stmt->execute($params);

            $this->sendSuccess(['message' => 'Profilo aggiornato con successo']);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Termina tutte le sessioni dell'utente
     */
    private function terminateAllSessions(): void {
        if (!$this->auth->isAuthenticated()) {
            $this->sendError('Non autenticato', 401);
            return;
        }

        // Valida CSRF
        if (!$this->validateCSRF()) {
            $this->sendError('Token CSRF non valido', 403);
            return;
        }

        try {
            $user = $this->auth->getCurrentUser();
            $currentSessionId = session_id();

            $db = getDbConnection();
            $stmt = $db->prepare("
                DELETE FROM user_sessions
                WHERE user_id = :user_id
                  AND id != :current_session
            ");

            $stmt->execute([
                'user_id' => $user['id'],
                'current_session' => $currentSessionId
            ]);

            $deletedCount = $stmt->rowCount();

            $this->sendSuccess([
                'message' => "Terminate $deletedCount sessioni",
                'sessions_terminated' => $deletedCount
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Imposta cookie di sessione sicuro
     */
    private function setSecureSessionCookie(): void {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            session_id(),
            [
                'expires' => time() + 86400, // 24 ore
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }

    /**
     * Rimuove cookie di sessione
     */
    private function removeSessionCookie(): void {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }

    /**
     * Valida token CSRF
     */
    private function validateCSRF(): bool {
        $token = $this->request['csrf_token'] ??
                 $_SERVER['HTTP_X_CSRF_TOKEN'] ??
                 $_POST['csrf_token'] ??
                 '';

        return !empty($token) && $this->auth->validateCSRFToken($token);
    }

    /**
     * Invia risposta di successo
     */
    private function sendSuccess($data): void {
        http_response_code(200);

        // Se data contiene già 'message', strutturalo diversamente per compatibilità
        if (is_array($data) && isset($data['message'])) {
            $response = array_merge(['success' => true], $data);
        } else {
            $response = [
                'success' => true,
                'data' => $data
            ];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Invia risposta di errore
     */
    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Esegui handler
$api = new AuthAPIV2();
$api->handle();