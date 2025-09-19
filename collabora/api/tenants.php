<?php declare(strict_types=1);

/**
 * Tenant Management API
 * Endpoint per la gestione dei tenant
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
require_once __DIR__ . '/../includes/TenantManager_v2.php';
require_once __DIR__ . '/../includes/UserManager.php';

use Collabora\Auth\AuthenticationV2;
use Collabora\Tenants\TenantManagerV2;
use Collabora\Users\UserManager;

/**
 * Classe per gestire le richieste API dei tenant
 */
class TenantsAPI {
    private AuthenticationV2 $auth;
    private TenantManagerV2 $tenantManager;
    private UserManager $userManager;
    private array $request;
    private string $method;
    private array $urlParts;

    public function __construct() {
        $this->auth = new AuthenticationV2();
        $this->tenantManager = new TenantManagerV2();
        $this->userManager = new UserManager();
        $this->method = $_SERVER['REQUEST_METHOD'];

        // Parse URL
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $basePath = '/api/tenants';
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

        // Merge GET parameters
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
        // GET /api/tenants - Lista tenant
        if (empty($this->urlParts)) {
            $this->listTenants();
            return;
        }

        // GET /api/tenants/available - Tenant disponibili per l'utente
        if ($this->urlParts[0] === 'available') {
            $this->getAvailableTenants();
            return;
        }

        // GET /api/tenants/current - Tenant corrente
        if ($this->urlParts[0] === 'current') {
            $this->getCurrentTenant();
            return;
        }

        // GET /api/tenants/stats - Statistiche generali
        if ($this->urlParts[0] === 'stats' && $this->auth->isAdmin()) {
            $this->getTenantStats();
            return;
        }

        // GET /api/tenants/{id} - Dettaglio tenant
        if (count($this->urlParts) === 1 && is_numeric($this->urlParts[0])) {
            $this->getTenant((int)$this->urlParts[0]);
            return;
        }

        // GET /api/tenants/{id}/users - Utenti di un tenant
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'users') {
            $this->getTenantUsers((int)$this->urlParts[0]);
            return;
        }

        // GET /api/tenants/{id}/storage - Info storage
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'storage') {
            $this->getTenantStorage((int)$this->urlParts[0]);
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

        // POST /api/tenants - Crea nuovo tenant
        if (empty($this->urlParts)) {
            $this->createTenant();
            return;
        }

        // POST /api/tenants/switch - Cambia tenant attivo
        if ($this->urlParts[0] === 'switch') {
            $this->switchTenant();
            return;
        }

        // POST /api/tenants/{id}/users - Aggiungi utente al tenant
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'users') {
            $this->addUserToTenant((int)$this->urlParts[0]);
            return;
        }

        // POST /api/tenants/{id}/owner - Assegna owner
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'owner') {
            $this->assignOwner((int)$this->urlParts[0]);
            return;
        }

        // POST /api/tenants/{id}/suspend - Sospendi tenant
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'suspend') {
            $this->suspendTenant((int)$this->urlParts[0]);
            return;
        }

        // POST /api/tenants/{id}/activate - Attiva tenant
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'activate') {
            $this->activateTenant((int)$this->urlParts[0]);
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

        // PUT /api/tenants/{id} - Aggiorna tenant
        if (count($this->urlParts) === 1 && is_numeric($this->urlParts[0])) {
            $this->updateTenant((int)$this->urlParts[0]);
            return;
        }

        // PUT /api/tenants/{id}/settings - Aggiorna impostazioni
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'settings') {
            $this->updateTenantSettings((int)$this->urlParts[0]);
            return;
        }

        // PUT /api/tenants/{id}/subscription - Aggiorna sottoscrizione
        if (count($this->urlParts) === 2 && is_numeric($this->urlParts[0]) && $this->urlParts[1] === 'subscription') {
            $this->updateTenantSubscription((int)$this->urlParts[0]);
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

        // DELETE /api/tenants/{id} - Elimina tenant
        if (count($this->urlParts) === 1 && is_numeric($this->urlParts[0])) {
            $this->deleteTenant((int)$this->urlParts[0]);
            return;
        }

        // DELETE /api/tenants/{tenantId}/users/{userId} - Rimuovi utente dal tenant
        if (count($this->urlParts) === 3 &&
            is_numeric($this->urlParts[0]) &&
            $this->urlParts[1] === 'users' &&
            is_numeric($this->urlParts[2])) {
            $this->removeUserFromTenant((int)$this->urlParts[0], (int)$this->urlParts[2]);
            return;
        }

        $this->sendError('Endpoint non trovato', 404);
    }

    /**
     * Lista tutti i tenant
     */
    private function listTenants(): void {
        try {
            $filters = [];

            // Applica filtri
            if (isset($this->request['search'])) {
                $filters['search'] = $this->request['search'];
            }
            if (isset($this->request['status'])) {
                $filters['status'] = $this->request['status'];
            }
            if (isset($this->request['subscription_tier'])) {
                $filters['subscription_tier'] = $this->request['subscription_tier'];
            }

            $page = isset($this->request['page']) ? max(1, (int)$this->request['page']) : 1;
            $limit = isset($this->request['limit']) ? min(100, max(1, (int)$this->request['limit'])) : 20;

            $result = $this->tenantManager->searchTenants($filters, $page, $limit);
            $this->sendSuccess($result);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Ottiene i tenant disponibili per l'utente
     */
    private function getAvailableTenants(): void {
        try {
            $tenants = $this->tenantManager->getUserAvailableTenants();
            $this->sendSuccess([
                'tenants' => $tenants,
                'current_tenant_id' => $this->auth->getCurrentTenantId(),
                'can_switch' => $this->auth->canSwitchTenant($this->auth->getCurrentUser())
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Ottiene il tenant corrente
     */
    private function getCurrentTenant(): void {
        try {
            $tenantId = $this->auth->getCurrentTenantId();

            if (!$tenantId) {
                $this->sendSuccess(['tenant' => null]);
                return;
            }

            $tenant = $this->tenantManager->getTenantById($tenantId);
            $this->sendSuccess(['tenant' => $tenant]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Ottiene un singolo tenant
     */
    private function getTenant(int $tenantId): void {
        try {
            $tenant = $this->tenantManager->getTenantById($tenantId);

            if (!$tenant) {
                $this->sendError('Tenant non trovato', 404);
                return;
            }

            $this->sendSuccess($tenant);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Crea un nuovo tenant
     */
    private function createTenant(): void {
        try {
            // Solo admin possono creare tenant
            if (!$this->auth->hasPermission('tenants.create')) {
                $this->sendError('Non hai i permessi per creare tenant', 403);
                return;
            }

            // Valida campi obbligatori
            if (empty($this->request['name'])) {
                $this->sendError('Nome tenant obbligatorio', 400);
                return;
            }

            $tenant = $this->tenantManager->createTenant($this->request);
            $this->sendSuccess([
                'message' => 'Tenant creato con successo',
                'tenant' => $tenant
            ], 201);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Aggiorna un tenant
     */
    private function updateTenant(int $tenantId): void {
        try {
            $tenant = $this->tenantManager->updateTenant($tenantId, $this->request);
            $this->sendSuccess([
                'message' => 'Tenant aggiornato con successo',
                'tenant' => $tenant
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Elimina un tenant
     */
    private function deleteTenant(int $tenantId): void {
        try {
            // Solo admin possono eliminare tenant
            if (!$this->auth->hasPermission('tenants.delete')) {
                $this->sendError('Non hai i permessi per eliminare tenant', 403);
                return;
            }

            $success = $this->tenantManager->deleteTenant($tenantId);

            if ($success) {
                $this->sendSuccess(['message' => 'Tenant eliminato con successo']);
            } else {
                $this->sendError('Impossibile eliminare il tenant', 400);
            }

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Cambia tenant attivo
     */
    private function switchTenant(): void {
        try {
            if (empty($this->request['tenant_id'])) {
                $this->sendError('ID tenant obbligatorio', 400);
                return;
            }

            $success = $this->tenantManager->switchToTenant((int)$this->request['tenant_id']);

            if ($success) {
                $tenant = $this->tenantManager->getTenantById((int)$this->request['tenant_id']);
                $this->sendSuccess([
                    'message' => 'Tenant cambiato con successo',
                    'tenant' => $tenant
                ]);
            } else {
                $this->sendError('Impossibile cambiare tenant', 400);
            }

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Ottiene gli utenti di un tenant
     */
    private function getTenantUsers(int $tenantId): void {
        try {
            // Verifica accesso al tenant
            $tenant = $this->tenantManager->getTenantById($tenantId);
            if (!$tenant) {
                $this->sendError('Tenant non trovato', 404);
                return;
            }

            $filters = ['tenant_id' => $tenantId];
            $result = $this->userManager->searchUsers($filters, 1, 100);

            $this->sendSuccess([
                'tenant_id' => $tenantId,
                'tenant_name' => $tenant['name'],
                'users' => $result['users'],
                'total' => $result['pagination']['total']
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Aggiunge un utente al tenant
     */
    private function addUserToTenant(int $tenantId): void {
        try {
            if (empty($this->request['user_id'])) {
                $this->sendError('ID utente obbligatorio', 400);
                return;
            }

            $success = $this->userManager->associateUserToTenant(
                (int)$this->request['user_id'],
                $tenantId,
                $this->request
            );

            if ($success) {
                $this->sendSuccess(['message' => 'Utente aggiunto al tenant con successo']);
            } else {
                $this->sendError('Impossibile aggiungere l\'utente al tenant', 400);
            }

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Rimuove un utente dal tenant
     */
    private function removeUserFromTenant(int $tenantId, int $userId): void {
        try {
            $success = $this->userManager->removeUserFromTenant($userId, $tenantId);

            if ($success) {
                $this->sendSuccess(['message' => 'Utente rimosso dal tenant con successo']);
            } else {
                $this->sendError('Impossibile rimuovere l\'utente dal tenant', 400);
            }

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Assegna un owner al tenant
     */
    private function assignOwner(int $tenantId): void {
        try {
            if (empty($this->request['user_id'])) {
                $this->sendError('ID utente obbligatorio', 400);
                return;
            }

            $success = $this->tenantManager->assignOwner($tenantId, (int)$this->request['user_id']);

            if ($success) {
                $this->sendSuccess(['message' => 'Owner assegnato con successo']);
            } else {
                $this->sendError('Impossibile assegnare l\'owner', 400);
            }

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Ottiene info storage del tenant
     */
    private function getTenantStorage(int $tenantId): void {
        try {
            $tenant = $this->tenantManager->getTenantById($tenantId);

            if (!$tenant) {
                $this->sendError('Tenant non trovato', 404);
                return;
            }

            $this->sendSuccess([
                'tenant_id' => $tenantId,
                'storage_quota_gb' => $tenant['storage_quota_gb'],
                'storage_used_gb' => $tenant['storage_used_gb'] ?? 0,
                'storage_percentage' => $tenant['storage_percentage'] ?? 0,
                'storage_available_gb' => $tenant['storage_quota_gb'] - ($tenant['storage_used_gb'] ?? 0)
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Aggiorna le impostazioni del tenant
     */
    private function updateTenantSettings(int $tenantId): void {
        try {
            if (empty($this->request['settings'])) {
                $this->sendError('Impostazioni obbligatorie', 400);
                return;
            }

            $tenant = $this->tenantManager->updateTenant($tenantId, [
                'settings' => $this->request['settings']
            ]);

            $this->sendSuccess([
                'message' => 'Impostazioni aggiornate con successo',
                'tenant' => $tenant
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Aggiorna la sottoscrizione del tenant
     */
    private function updateTenantSubscription(int $tenantId): void {
        try {
            // Solo admin possono modificare sottoscrizioni
            if (!$this->auth->isAdmin()) {
                $this->sendError('Solo gli amministratori possono modificare le sottoscrizioni', 403);
                return;
            }

            $updates = [];
            if (isset($this->request['subscription_tier'])) {
                $updates['subscription_tier'] = $this->request['subscription_tier'];
            }
            if (isset($this->request['subscription_expires_at'])) {
                $updates['subscription_expires_at'] = $this->request['subscription_expires_at'];
            }
            if (isset($this->request['storage_quota_gb'])) {
                $updates['storage_quota_gb'] = $this->request['storage_quota_gb'];
            }
            if (isset($this->request['max_users'])) {
                $updates['max_users'] = $this->request['max_users'];
            }

            $tenant = $this->tenantManager->updateTenant($tenantId, $updates);

            $this->sendSuccess([
                'message' => 'Sottoscrizione aggiornata con successo',
                'tenant' => $tenant
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Sospende un tenant
     */
    private function suspendTenant(int $tenantId): void {
        try {
            // Solo admin possono sospendere tenant
            if (!$this->auth->isAdmin()) {
                $this->sendError('Solo gli amministratori possono sospendere tenant', 403);
                return;
            }

            $tenant = $this->tenantManager->updateTenant($tenantId, ['status' => 'suspended']);

            $this->sendSuccess([
                'message' => 'Tenant sospeso con successo',
                'tenant' => $tenant
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Attiva un tenant
     */
    private function activateTenant(int $tenantId): void {
        try {
            // Solo admin possono attivare tenant
            if (!$this->auth->isAdmin()) {
                $this->sendError('Solo gli amministratori possono attivare tenant', 403);
                return;
            }

            $tenant = $this->tenantManager->updateTenant($tenantId, ['status' => 'active']);

            $this->sendSuccess([
                'message' => 'Tenant attivato con successo',
                'tenant' => $tenant
            ]);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * Ottiene statistiche generali sui tenant
     */
    private function getTenantStats(): void {
        try {
            $db = getDbConnection();

            // Statistiche generali
            $stmt = $db->query("
                SELECT
                    COUNT(*) as total_tenants,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_tenants,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_tenants,
                    SUM(storage_used_bytes) / 1073741824 as total_storage_gb,
                    AVG(storage_used_bytes / 1073741824) as avg_storage_gb
                FROM tenants
                WHERE deleted_at IS NULL
            ");

            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Statistiche per tier
            $stmt = $db->query("
                SELECT subscription_tier, COUNT(*) as count
                FROM tenants
                WHERE deleted_at IS NULL
                GROUP BY subscription_tier
            ");

            $stats['by_tier'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Ultimi tenant creati
            $stmt = $db->query("
                SELECT id, name, created_at
                FROM tenants
                WHERE deleted_at IS NULL
                ORDER BY created_at DESC
                LIMIT 5
            ");

            $stats['recent_tenants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->sendSuccess($stats);

        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
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
$api = new TenantsAPI();
$api->handle();