<?php declare(strict_types=1);

/**
 * Authentication System V2
 * Role-based authentication with multi-tenant support
 *
 * @author Nexiosolution
 * @version 2.0.0
 * @since 2025-01-17
 */

namespace Collabora\Auth;

use PDO;
use PDOException;
use Exception;
use RuntimeException;
use InvalidArgumentException;

require_once __DIR__ . '/db.php';

/**
 * Enum per i ruoli utente
 */
enum UserRole: string {
    case ADMIN = 'admin';
    case SPECIAL_USER = 'special_user';
    case STANDARD_USER = 'standard_user';
}

/**
 * Enum per lo stato utente
 */
enum UserStatus: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case LOCKED = 'locked';
}

/**
 * Classe principale per l'autenticazione
 */
class AuthenticationV2 {
    private PDO $db;
    private ?array $currentUser = null;
    private ?int $currentTenantId = null;
    private const SESSION_NAME = 'COLLABORA_SESSID';
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900; // 15 minuti
    private const SESSION_LIFETIME = 86400; // 24 ore
    private const SESSION_RENEWAL_THRESHOLD = 3600; // 1 ora

    /**
     * Costruttore
     */
    public function __construct() {
        $this->db = getDbConnection();
        $this->initializeSession();
    }

    /**
     * Inizializza la sessione PHP con configurazioni di sicurezza
     */
    private function initializeSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Configura solo se non sono stati inviati headers
            if (!headers_sent()) {
                ini_set('session.use_strict_mode', '1');
                ini_set('session.use_only_cookies', '1');
                ini_set('session.cookie_httponly', '1');
                ini_set('session.cookie_samesite', 'Strict');
                ini_set('session.cookie_lifetime', (string)self::SESSION_LIFETIME);
                ini_set('session.gc_maxlifetime', (string)self::SESSION_LIFETIME);
                ini_set('session.name', self::SESSION_NAME);

                if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                    ini_set('session.cookie_secure', '1');
                }
            }

            if (!headers_sent()) {
                session_start();
            }

            // Rigenera l'ID di sessione periodicamente per sicurezza
            if (!isset($_SESSION['last_regenerate'])) {
                $_SESSION['last_regenerate'] = time();
            } elseif (time() - $_SESSION['last_regenerate'] > self::SESSION_RENEWAL_THRESHOLD) {
                session_regenerate_id(true);
                $_SESSION['last_regenerate'] = time();
            }
        }
    }

    /**
     * Autentica un utente con email e password
     *
     * @param string $email Email dell'utente
     * @param string $password Password in chiaro
     * @return array Dati utente e tenant disponibili
     * @throws Exception Se l'autenticazione fallisce
     */
    public function login(string $email, string $password): array {
        try {
            // Valida input
            $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
            if (!$email) {
                throw new InvalidArgumentException('Email non valida');
            }

            if (strlen($password) < 8) {
                throw new InvalidArgumentException('Password non valida');
            }

            // Recupera utente
            $stmt = $this->db->prepare("
                SELECT id, email, password, first_name, last_name, role, is_system_admin,
                       status, tenant_id, failed_login_attempts, locked_until,
                       email_verified_at, settings, timezone, language
                FROM users
                WHERE email = :email
                  AND deleted_at IS NULL
            ");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $this->logActivity('login.failed', null, null, ['email' => $email, 'reason' => 'user_not_found']);
                throw new Exception('Credenziali non valide');
            }

            // Verifica stato account
            if ($user['status'] === UserStatus::LOCKED->value) {
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $this->logActivity('login.blocked', $user['id'], null, ['reason' => 'account_locked']);
                    throw new Exception('Account temporaneamente bloccato. Riprova più tardi.');
                }
            }

            if ($user['status'] === UserStatus::INACTIVE->value) {
                $this->logActivity('login.blocked', $user['id'], null, ['reason' => 'account_inactive']);
                throw new Exception('Account non attivo. Contatta l\'amministratore.');
            }

            // Verifica password
            if (!password_verify($password, $user['password'])) {
                $this->handleFailedLogin($user['id']);
                $this->logActivity('login.failed', $user['id'], null, ['reason' => 'invalid_password']);
                throw new Exception('Credenziali non valide');
            }

            // Reset tentativi falliti
            $this->resetLoginAttempts($user['id']);

            // Aggiorna ultimo login
            $this->updateLastLogin($user['id']);

            // Ottieni tenant disponibili
            $tenants = $this->getUserTenants($user['id'], $user['role'], (bool)$user['is_system_admin']);

            // Imposta sessione
            $this->setupUserSession($user, $tenants);

            // Log attività
            $this->logActivity('login.success', $user['id'], $this->currentTenantId);

            return [
                'success' => true,
                'user' => $this->sanitizeUserData($user),
                'tenants' => $tenants,
                'current_tenant_id' => $this->currentTenantId,
                'session_id' => session_id()
            ];

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Disconnette l'utente corrente
     */
    public function logout(): void {
        if ($this->isAuthenticated()) {
            $userId = $_SESSION['user_id'] ?? null;
            $tenantId = $_SESSION['current_tenant_id'] ?? null;

            // Rimuovi sessione dal database
            $this->removeSessionFromDatabase(session_id());

            // Log attività
            $this->logActivity('logout', $userId, $tenantId);

            // Distruggi sessione PHP
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            session_destroy();
        }
    }

    /**
     * Verifica se l'utente è autenticato
     */
    public function isAuthenticated(): bool {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }

        // Verifica token di sessione
        $stmt = $this->db->prepare("
            SELECT id, last_activity
            FROM user_sessions
            WHERE id = :session_id
              AND user_id = :user_id
        ");
        $stmt->execute([
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id']
        ]);

        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            $this->logout();
            return false;
        }

        // Aggiorna attività
        $this->updateSessionActivity();

        return true;
    }

    /**
     * Ottiene l'utente corrente
     */
    public function getCurrentUser(): ?array {
        if (!$this->isAuthenticated()) {
            return null;
        }

        if ($this->currentUser === null) {
            $stmt = $this->db->prepare("
                SELECT id, email, first_name, last_name, role, is_system_admin,
                       tenant_id, settings, timezone, language
                FROM users
                WHERE id = :id
                  AND status = 'active'
                  AND deleted_at IS NULL
            ");
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $this->currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $this->currentUser;
    }

    /**
     * Ottiene il tenant corrente
     */
    public function getCurrentTenantId(): ?int {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return $_SESSION['current_tenant_id'] ?? null;
    }

    /**
     * Cambia il tenant attivo (solo per special_user e admin)
     */
    public function switchTenant(int $tenantId): bool {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new Exception('Utente non autenticato');
        }

        // Verifica permessi
        if (!$this->canSwitchTenant($user)) {
            throw new Exception('Non hai i permessi per cambiare tenant');
        }

        // Verifica accesso al tenant
        if (!$this->hasAccessToTenant($user['id'], $tenantId, $user['role'], (bool)$user['is_system_admin'])) {
            throw new Exception('Non hai accesso a questo tenant');
        }

        // Aggiorna sessione
        $_SESSION['current_tenant_id'] = $tenantId;
        $this->currentTenantId = $tenantId;

        // Aggiorna database
        $stmt = $this->db->prepare("
            UPDATE user_sessions
            SET current_tenant_id = :tenant_id,
                last_activity = NOW()
            WHERE id = :session_id
        ");
        $stmt->execute([
            'tenant_id' => $tenantId,
            'session_id' => session_id()
        ]);

        // Aggiorna last_accessed_at nella tabella associazioni
        $stmt = $this->db->prepare("
            UPDATE user_tenant_associations
            SET last_accessed_at = NOW()
            WHERE user_id = :user_id AND tenant_id = :tenant_id
        ");
        $stmt->execute([
            'user_id' => $user['id'],
            'tenant_id' => $tenantId
        ]);

        // Log attività
        $this->logActivity('tenant.switch', $user['id'], $tenantId);

        return true;
    }

    /**
     * Verifica se l'utente ha un determinato permesso
     */
    public function hasPermission(string $permission): bool {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }

        // Admin ha tutti i permessi
        if ((bool)$user['is_system_admin']) {
            return true;
        }

        // Ottieni permessi per il ruolo
        $permissions = $this->getRolePermissions($user['role']);

        // Verifica permesso esatto o wildcard
        foreach ($permissions as $perm) {
            if ($perm === $permission) {
                return true;
            }

            // Supporta wildcard (es: users.* permette users.create, users.edit, etc)
            if (str_ends_with($perm, '.*')) {
                $prefix = substr($perm, 0, -2);
                if (str_starts_with($permission, $prefix . '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verifica se l'utente è admin
     */
    public function isAdmin(): bool {
        $user = $this->getCurrentUser();
        return $user && ((bool)($user['is_system_admin'] ?? false) || $user['role'] === UserRole::ADMIN->value);
    }

    /**
     * Verifica se l'utente può cambiare tenant
     */
    public function canSwitchTenant(array $user): bool {
        return (bool)($user['is_system_admin'] ?? false) ||
               $user['role'] === UserRole::ADMIN->value ||
               $user['role'] === UserRole::SPECIAL_USER->value;
    }

    /**
     * Ottiene i tenant disponibili per l'utente
     */
    private function getUserTenants(int $userId, string $role, bool $isSystemAdmin): array {
        // Admin di sistema può accedere a tutti i tenant
        if ($isSystemAdmin) {
            $stmt = $this->db->prepare("
                SELECT id, name, domain, status, settings
                FROM tenants
                WHERE status = 'active'
                  AND deleted_at IS NULL
                ORDER BY name
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Altri utenti ottengono i tenant associati
        $stmt = $this->db->prepare("
            SELECT t.id, t.name, t.domain, t.status, t.settings,
                   uta.role_in_tenant, uta.is_primary, uta.permissions
            FROM user_tenant_associations uta
            JOIN tenants t ON t.id = uta.tenant_id
            WHERE uta.user_id = :user_id
              AND t.status = 'active'
            ORDER BY uta.is_primary DESC, t.name
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se l'utente ha accesso a un tenant
     */
    private function hasAccessToTenant(int $userId, int $tenantId, string $role, bool $isSystemAdmin): bool {
        if ($isSystemAdmin) {
            return true;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM user_tenant_associations
            WHERE user_id = :user_id
              AND tenant_id = :tenant_id
        ");
        $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Configura la sessione utente dopo il login
     */
    private function setupUserSession(array $user, array $tenants): void {
        // Dati base sessione
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['is_admin'] = (bool)$user['is_system_admin'];
        $_SESSION['session_token'] = bin2hex(random_bytes(32));
        $_SESSION['login_time'] = time();

        // Determina tenant iniziale
        if ($user['is_system_admin']) {
            // Admin può non avere un tenant selezionato inizialmente
            $this->currentTenantId = null;
        } elseif (!empty($tenants)) {
            // Usa il tenant primario o il primo disponibile
            $primaryTenant = array_filter($tenants, fn($t) => $t['is_primary'] ?? false);
            $this->currentTenantId = !empty($primaryTenant)
                ? (int)array_values($primaryTenant)[0]['id']
                : (int)$tenants[0]['id'];
        } else {
            $this->currentTenantId = $user['tenant_id'];
        }

        $_SESSION['current_tenant_id'] = $this->currentTenantId;

        // Salva sessione nel database
        $this->saveSessionToDatabase($user['id'], $this->currentTenantId);
    }

    /**
     * Salva la sessione nel database
     */
    private function saveSessionToDatabase(int $userId, ?int $tenantId): void {
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (id, user_id, current_tenant_id, ip_address, user_agent, last_activity)
            VALUES (:id, :user_id, :tenant_id, :ip, :user_agent, NOW())
            ON DUPLICATE KEY UPDATE
                current_tenant_id = VALUES(current_tenant_id),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                last_activity = NOW()
        ");

        $stmt->execute([
            'id' => session_id(),
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }

    /**
     * Rimuove la sessione dal database
     */
    private function removeSessionFromDatabase(string $sessionId): void {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE id = :id");
        $stmt->execute(['id' => $sessionId]);
    }

    /**
     * Aggiorna l'attività della sessione
     */
    private function updateSessionActivity(): void {
        $stmt = $this->db->prepare("
            UPDATE user_sessions
            SET last_activity = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => session_id()]);
    }

    /**
     * Gestisce un tentativo di login fallito
     */
    private function handleFailedLogin(int $userId): void {
        $stmt = $this->db->prepare("
            UPDATE users
            SET failed_login_attempts = failed_login_attempts + 1,
                locked_until = IF(
                    failed_login_attempts >= :max_attempts - 1,
                    DATE_ADD(NOW(), INTERVAL :lockout SECOND),
                    locked_until
                )
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $userId,
            'max_attempts' => self::MAX_LOGIN_ATTEMPTS,
            'lockout' => self::LOCKOUT_DURATION
        ]);
    }

    /**
     * Resetta i tentativi di login falliti
     */
    private function resetLoginAttempts(int $userId): void {
        $stmt = $this->db->prepare("
            UPDATE users
            SET failed_login_attempts = 0,
                locked_until = NULL
            WHERE id = :id
        ");
        $stmt->execute(['id' => $userId]);
    }

    /**
     * Aggiorna l'ultimo login dell'utente
     */
    private function updateLastLogin(int $userId): void {
        $stmt = $this->db->prepare("
            UPDATE users
            SET last_login = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $userId
        ]);
    }

    /**
     * Ottiene i permessi per un ruolo
     */
    private function getRolePermissions(string $role): array {
        static $cache = [];

        if (isset($cache[$role])) {
            return $cache[$role];
        }

        $stmt = $this->db->prepare("
            SELECT permissions
            FROM permission_sets
            WHERE name = :role
        ");
        $stmt->execute(['role' => $role]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && $result['permissions']) {
            $cache[$role] = json_decode($result['permissions'], true);
            return $cache[$role];
        }

        return [];
    }

    /**
     * Registra un'attività nel log
     */
    private function logActivity(
        string $action,
        ?int $userId = null,
        ?int $tenantId = null,
        ?array $metadata = null
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, tenant_id, action, ip_address, user_agent, session_id, metadata)
                VALUES (:user_id, :tenant_id, :action, :ip, :user_agent, :session_id, :metadata)
            ");

            $stmt->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'action' => $action,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'session_id' => session_id(),
                'metadata' => $metadata ? json_encode($metadata) : null
            ]);
        } catch (PDOException $e) {
            // Log silenzioso degli errori
            error_log("Activity log error: " . $e->getMessage());
        }
    }

    /**
     * Pulisce i dati utente per la risposta
     */
    private function sanitizeUserData(array $user): array {
        unset(
            $user['password'],
            $user['two_factor_secret'],
            $user['failed_login_attempts'],
            $user['locked_until']
        );
        return $user;
    }

    /**
     * Genera un token CSRF per i form
     */
    public function generateCSRFToken(): string {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        return $token;
    }

    /**
     * Valida un token CSRF
     */
    public function validateCSRFToken(string $token): bool {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }

        // Token scade dopo 1 ora
        if (time() - $_SESSION['csrf_token_time'] > 3600) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Helper functions per retrocompatibilità
function isAuthenticatedV2(): bool {
    $auth = new AuthenticationV2();
    return $auth->isAuthenticated();
}

function requireAuthV2(): void {
    if (!isAuthenticatedV2()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Non autenticato']);
        exit;
    }
}

function requirePermissionV2(string $permission): void {
    requireAuthV2();
    $auth = new AuthenticationV2();

    if (!$auth->hasPermission($permission)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Permesso negato']);
        exit;
    }
}

function requireAdminV2(): void {
    requireAuthV2();
    $auth = new AuthenticationV2();

    if (!$auth->isAdmin()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Richiesti privilegi di amministratore']);
        exit;
    }
}