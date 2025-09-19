<?php declare(strict_types=1);

/**
 * User Management API
 * Endpoint per la gestione utenti (admin only)
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

require_once __DIR__ . '/../includes/auth_v2.php';
require_once __DIR__ . '/../includes/UserManager.php';
require_once __DIR__ . '/../includes/TenantManager_v2.php';

use Collabora\Auth\AuthenticationV2;
use Collabora\Users\UserManager;
use Collabora\Tenants\TenantManagerV2;

/**
 * Classe per gestire le richieste API utenti
 */
class UsersAPI {
    private AuthenticationV2 $auth;
    private UserManager $userManager;
    private TenantManagerV2 $tenantManager;
    private array $request;
    private string $method;
    private array $urlParts;

    public function __construct() {
        $this->auth = new AuthenticationV2();
        $this->userManager = new UserManager();
        $this->tenantManager = new TenantManagerV2();
        $this->method = $_SERVER['REQUEST_METHOD'];

        // Parse URL
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $basePath = '/api/users';
        $endpoint = str_replace($basePath, '', $path);
        $this->urlParts = array_filter(explode('/', trim($endpoint, '/')));

        // Parse request body
        $this->request = [];
        if (in_array($this->method, ['POST', 'PUT', 'PATCH'])) {
            $input = file_get_contents('php://input');
            if ($input) {
                $this->request = json_decode($input, true) ?? [];
            }
        }

        // Merge GET parameters for filtering
        if ($this->method === 'GET') {
            $this->request = array_merge($this->request, $_GET);
        }
    }

    /**
     * Processa la richiesta
     */
    public function handle(): void {
        try {
            // Verifica autenticazione
            if (!$this->auth->isAuthenticated()) {
                $this->sendError('Non autenticato', 401);
                return;
            }

            // Router
            switch ($this->method) {
                case 'GET':
                    $this->handleGet();
                    break;

                case 'POST':
                    $this->handlePost();
                    break;

                case 'PUT':
                case 'PATCH':
                    $this->handleUpdate();
                    break;

                case 'DELETE':
                    $this->handleDelete();
                    break;

                default:
                    $this->sendError('Metodo non supportato', 405);
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Gestisce richieste GET
     */
    private function handleGet(): void {
        // GET /api/users - Lista utenti
        if (empty($this->urlParts)) {
            $this->listUsers();
            return;
        }

        // GET /api/users/{id} - Dettaglio utente
        if (count($this->urlParts) === 1 && is_numeric($this->urlParts[0])) {
            $this->getUser((int)$this->urlParts[0]);
            return;
        }

        // GET /api/users/{id}/tenants - Tenant di un utente
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'tenants') {
            $this->getUserTenants((int)$this->urlParts[0]);
            return;
        }

        // GET /api/users/search - Ricerca utenti
        if ($this->urlParts[0] === 'search') {
            $this->searchUsers();
            return;
        }

        // GET /api/users/roles - Lista ruoli disponibili
        if ($this->urlParts[0] === 'roles') {
            $this->getAvailableRoles();
            return;
        }

        $this->sendError('Endpoint non trovato', 404);
    }

    /**
     * Gestisce richieste POST
     */
    private function handlePost(): void {
        // Valida CSRF token
        if (!$this->validateCSRF()) {
            $this->sendError('Token CSRF non valido', 403);
            return;
        }

        // POST /api/users - Crea nuovo utente
        if (empty($this->urlParts)) {
            $this->createUser();
            return;
        }

        // POST /api/users/{id}/tenants - Associa utente a tenant
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'tenants') {
            $this->associateUserToTenant((int)$this->urlParts[0]);
            return;
        }

        // POST /api/users/bulk - Creazione multipla
        if ($this->urlParts[0] === 'bulk') {
            $this->bulkCreateUsers();
            return;
        }

        // POST /api/users/{id}/reset-password - Reset password
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'reset-password') {
            $this->resetUserPassword((int)$this->urlParts[0]);
            return;
        }

        // POST /api/users/{id}/activate - Attiva utente
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'activate') {
            $this->activateUser((int)$this->urlParts[0]);
            return;
        }

        // POST /api/users/{id}/deactivate - Disattiva utente
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'deactivate') {
            $this->deactivateUser((int)$this->urlParts[0]);
            return;
        }

        $this->sendError('Endpoint non trovato', 404);
    }

    /**
     * Gestisce richieste PUT/PATCH
     */
    private function handleUpdate(): void {
        // Valida CSRF token
        if (!$this->validateCSRF()) {
            $this->sendError('Token CSRF non valido', 403);
            return;
        }

        // PUT /api/users/{id} - Aggiorna utente
        if (count($this->urlParts) === 1 && is_numeric($this->urlParts[0])) {
            $this->updateUser((int)$this->urlParts[0]);
            return;
        }

        // PUT /api/users/{id}/role - Cambia ruolo
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'role') {
            $this->updateUserRole((int)$this->urlParts[0]);
            return;
        }

        $this->sendError('Endpoint non trovato', 404);
    }

    /**
     * Gestisce richieste DELETE
     */
    private function handleDelete(): void {
        // Valida CSRF token
        if (!$this->validateCSRF()) {
            $this->sendError('Token CSRF non valido', 403);
            return;
        }

        // DELETE /api/users/{id} - Elimina utente
        if (count($this->urlParts) === 1 && is_numeric($this->urlParts[0])) {
            $this->deleteUser((int)$this->urlParts[0]);
            return;
        }

        // DELETE /api/users/{userId}/tenants/{tenantId} - Rimuovi associazione
        if (count($this->urlParts) === 3 &&
            is_numeric($this->urlParts[0]) &&
            $this->urlParts[1] === 'tenants' &&
            is_numeric($this->urlParts[2])) {
            $this->removeUserFromTenant((int)$this->urlParts[0], (int)$this->urlParts[2]);
            return;
        }

        $this->sendError('Endpoint non trovato', 404);
    }

    /**
     * Lista tutti gli utenti
     */
    private function listUsers(): void {
        try {
            $filters = [];

            // Applica filtri dalla query string
            if (isset($this->request['search'])) {
                $filters['search'] = $this->request['search'];
            }
            if (isset($this->request['role'])) {
                $filters['role'] = $this->request['role'];
            }
            if (isset($this->request['status'])) {
                $filters['status'] = $this->request['status'];
            }
            if (isset($this->request['tenant_id'])) {
                $filters['tenant_id'] = (int)$this->request['tenant_id'];
            }

            $page = isset($this->request['page']) ? max(1, (int)$this->request['page']) : 1;
            $limit = isset($this->request['limit']) ? min(100, max(1, (int)$this->request['limit'])) : 20;

            $result = $this->userManager->searchUsers($filters, $page, $limit);
            $this->sendSuccess($result);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Ottiene un singolo utente
     */
    private function getUser(int $userId): void {
        try {
            $user = $this->userManager->getUserById($userId);

            if (!$user) {
                $this->sendError('Utente non trovato', 404);
                return;
            }

            $this->sendSuccess($user);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Crea un nuovo utente
     */
    private function createUser(): void {
        try {
            // Valida campi obbligatori
            $required = ['email', 'password', 'first_name', 'last_name'];
            foreach ($required as $field) {
                if (empty($this->request[$field])) {
                    $this->sendError("Campo '$field' obbligatorio", 400);
                    return;
                }
            }

            $user = $this->userManager->createUser($this->request);
            $this->sendSuccess([
                'message' => 'Utente creato con successo',
                'user' => $user
            ], 201);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Aggiorna un utente
     */
    private function updateUser(int $userId): void {
        try {
            $user = $this->userManager->updateUser($userId, $this->request);
            $this->sendSuccess([
                'message' => 'Utente aggiornato con successo',
                'user' => $user
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Elimina un utente
     */
    private function deleteUser(int $userId): void {
        try {
            $success = $this->userManager->deleteUser($userId);

            if ($success) {
                $this->sendSuccess(['message' => 'Utente eliminato con successo']);
            } else {
                $this->sendError('Impossibile eliminare l\'utente', 400);
            }

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Ottiene i tenant di un utente
     */
    private function getUserTenants(int $userId): void {
        try {
            $user = $this->userManager->getUserById($userId);

            if (!$user) {
                $this->sendError('Utente non trovato', 404);
                return;
            }

            $this->sendSuccess([
                'user_id' => $userId,
                'tenants' => $user['tenants'] ?? []
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Associa utente a tenant
     */
    private function associateUserToTenant(int $userId): void {
        try {
            if (empty($this->request['tenant_id'])) {
                $this->sendError('ID tenant obbligatorio', 400);
                return;
            }

            $success = $this->userManager->associateUserToTenant(
                $userId,
                (int)$this->request['tenant_id'],
                $this->request
            );

            if ($success) {
                $this->sendSuccess(['message' => 'Utente associato al tenant con successo']);
            } else {
                $this->sendError('Impossibile associare l\'utente al tenant', 400);
            }

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Rimuove associazione utente-tenant
     */
    private function removeUserFromTenant(int $userId, int $tenantId): void {
        try {
            $success = $this->userManager->removeUserFromTenant($userId, $tenantId);

            if ($success) {
                $this->sendSuccess(['message' => 'Associazione rimossa con successo']);
            } else {
                $this->sendError('Impossibile rimuovere l\'associazione', 400);
            }

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Cerca utenti
     */
    private function searchUsers(): void {
        try {
            $query = $this->request['q'] ?? $this->request['query'] ?? '';

            if (strlen($query) < 2) {
                $this->sendError('Query di ricerca troppo breve (minimo 2 caratteri)', 400);
                return;
            }

            $filters = ['search' => $query];
            $result = $this->userManager->searchUsers($filters, 1, 50);

            $this->sendSuccess($result);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Creazione multipla utenti
     */
    private function bulkCreateUsers(): void {
        try {
            if (empty($this->request['users']) || !is_array($this->request['users'])) {
                $this->sendError('Lista utenti non valida', 400);
                return;
            }

            $created = [];
            $errors = [];

            foreach ($this->request['users'] as $index => $userData) {
                try {
                    $user = $this->userManager->createUser($userData);
                    $created[] = $user;
                } catch (Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'email' => $userData['email'] ?? 'N/A',
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->sendSuccess([
                'created' => count($created),
                'failed' => count($errors),
                'users' => $created,
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Reset password utente
     */
    private function resetUserPassword(int $userId): void {
        try {
            // Genera password temporanea
            $tempPassword = $this->generateTempPassword();

            $this->userManager->updateUser($userId, [
                'password' => $tempPassword
            ]);

            // In produzione, inviare email con la nuova password
            $this->sendSuccess([
                'message' => 'Password resettata con successo',
                'temp_password' => $tempPassword // SOLO PER TEST! Rimuovere in produzione
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Attiva un utente
     */
    private function activateUser(int $userId): void {
        try {
            $this->userManager->updateUser($userId, ['status' => 'active']);
            $this->sendSuccess(['message' => 'Utente attivato con successo']);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Disattiva un utente
     */
    private function deactivateUser(int $userId): void {
        try {
            $this->userManager->updateUser($userId, ['status' => 'inactive']);
            $this->sendSuccess(['message' => 'Utente disattivato con successo']);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Aggiorna il ruolo di un utente
     */
    private function updateUserRole(int $userId): void {
        try {
            if (empty($this->request['role'])) {
                $this->sendError('Ruolo obbligatorio', 400);
                return;
            }

            $this->userManager->updateUser($userId, [
                'role' => $this->request['role']
            ]);

            $this->sendSuccess(['message' => 'Ruolo aggiornato con successo']);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Ottiene i ruoli disponibili
     */
    private function getAvailableRoles(): void {
        $roles = [
            [
                'value' => 'admin',
                'label' => 'Amministratore',
                'description' => 'Accesso completo al sistema',
                'permissions' => ['*']
            ],
            [
                'value' => 'special_user',
                'label' => 'Utente Speciale',
                'description' => 'Può accedere a più tenant',
                'permissions' => ['tenants.switch', 'tenants.view', 'files.*']
            ],
            [
                'value' => 'standard_user',
                'label' => 'Utente Standard',
                'description' => 'Accesso limitato a un singolo tenant',
                'permissions' => ['files.create', 'files.read', 'files.update_own', 'files.delete_own']
            ]
        ];

        $this->sendSuccess($roles);
    }

    /**
     * Genera password temporanea
     */
    private function generateTempPassword(): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }

    /**
     * Valida token CSRF
     */
    private function validateCSRF(): bool {
        $token = $this->request['csrf_token'] ??
                 $_SERVER['HTTP_X_CSRF_TOKEN'] ??
                 '';

        return !empty($token) && $this->auth->validateCSRFToken($token);
    }

    /**
     * Invia risposta di successo
     */
    private function sendSuccess(array $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
$api = new UsersAPI();
$api->handle();