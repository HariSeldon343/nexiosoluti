<?php
declare(strict_types=1);

/**
 * Sistema di autenticazione e gestione sessioni
 * Multi-tenant con protezione CSRF
 */

class Auth {
    private static ?array $currentUser = null;
    private static ?array $currentTenant = null;

    /**
     * Inizializza sessione con configurazioni sicure
     */
    public static function initSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Configurazione sicura sessione
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', SESSION_SAMESITE);
            ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);

            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => SESSION_PATH,
                'domain' => '',
                'secure' => SESSION_SECURE,
                'httponly' => SESSION_HTTPONLY,
                'samesite' => SESSION_SAMESITE
            ]);

            session_name(SESSION_NAME);
            session_start();

            // Rigenera ID sessione periodicamente
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minuti
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }

            // Pulizia sessioni vecchie
            if (mt_rand(1, 100) <= SESSION_CLEANUP_PROBABILITY) {
                self::cleanOldSessions();
            }
        }
    }

    /**
     * Login utente con credenziali
     */
    public static function login(string $username, string $password, string $tenantCode): bool {
        try {
            // Verifica tenant
            $tenant = Database::selectOne(
                "SELECT * FROM tenants WHERE code = :code AND status = 'active'",
                ['code' => $tenantCode]
            );

            if (!$tenant) {
                self::logFailedLogin($username, $tenantCode, 'invalid_tenant');
                return false;
            }

            // Verifica utente
            $user = Database::selectOne(
                "SELECT * FROM users
                WHERE tenant_id = :tenant_id
                AND (username = :username OR email = :username)
                AND status = 'active'",
                [
                    'tenant_id' => $tenant['id'],
                    'username' => $username
                ]
            );

            if (!$user || !password_verify($password, $user['password'])) {
                self::logFailedLogin($username, $tenantCode, 'invalid_credentials');
                return false;
            }

            // Check password rehashing
            if (password_needs_rehash($user['password'], PASSWORD_ALGO, PASSWORD_OPTIONS)) {
                $newHash = password_hash($password, PASSWORD_ALGO, PASSWORD_OPTIONS);
                Database::update('users', ['password' => $newHash], ['id' => $user['id']]);
            }

            // Imposta sessione
            self::setSession($user, $tenant);

            // Aggiorna last_login
            Database::update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

            // Log attività
            self::logActivity('login', 'user', $user['id'], [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            return true;

        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Logout utente
     */
    public static function logout(): void {
        if (self::isLoggedIn()) {
            $userId = $_SESSION['user_id'];
            $tenantId = $_SESSION['tenant_id'];

            // Log attività
            self::logActivity('logout', 'user', $userId);

            // Elimina sessione dal database
            Database::delete('sessions', ['id' => session_id()]);
        }

        // Pulisci sessione
        $_SESSION = [];

        // Distruggi cookie sessione
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

        // Distruggi sessione
        session_destroy();

        // Reset cache
        self::$currentUser = null;
        self::$currentTenant = null;
    }

    /**
     * Verifica se utente è loggato
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && isset($_SESSION['tenant_id']);
    }

    /**
     * Richiede autenticazione o reindirizza
     */
    public static function requireAuth(): void {
        if (!self::isLoggedIn()) {
            if (self::isAjaxRequest()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            } else {
                header('Location: /login.php');
                exit;
            }
        }

        // Verifica timeout sessione
        if (isset($_SESSION['last_activity']) &&
            time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
            self::logout();
            if (self::isAjaxRequest()) {
                http_response_code(401);
                echo json_encode(['error' => 'Session expired']);
                exit;
            } else {
                header('Location: /login.php?expired=1');
                exit;
            }
        }

        $_SESSION['last_activity'] = time();
    }

    /**
     * Verifica permesso utente
     */
    public static function checkPermission(string $permission): bool {
        if (!self::isLoggedIn()) {
            return false;
        }

        $user = self::getCurrentUser();
        if (!$user) {
            return false;
        }

        // Admin ha tutti i permessi
        if ($user['role'] === 'admin') {
            return true;
        }

        // Definizione permessi per ruolo
        $permissions = [
            'manager' => [
                'file.view', 'file.create', 'file.edit', 'file.delete',
                'folder.view', 'folder.create', 'folder.edit', 'folder.delete',
                'user.view'
            ],
            'user' => [
                'file.view', 'file.create', 'file.edit',
                'folder.view', 'folder.create'
            ]
        ];

        $rolePermissions = $permissions[$user['role']] ?? [];
        return in_array($permission, $rolePermissions);
    }

    /**
     * Richiede permesso specifico
     */
    public static function requirePermission(string $permission): void {
        if (!self::checkPermission($permission)) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['error' => 'Permission denied']);
                exit;
            } else {
                header('HTTP/1.1 403 Forbidden');
                echo 'Permission denied';
                exit;
            }
        }
    }

    /**
     * Ottiene utente corrente
     */
    public static function getCurrentUser(): ?array {
        if (self::$currentUser === null && self::isLoggedIn()) {
            self::$currentUser = Database::selectOne(
                'SELECT * FROM users WHERE id = :id',
                ['id' => $_SESSION['user_id']]
            );
        }
        return self::$currentUser;
    }

    /**
     * Ottiene tenant corrente
     */
    public static function getCurrentTenant(): ?array {
        if (self::$currentTenant === null && self::isLoggedIn()) {
            self::$currentTenant = Database::selectOne(
                'SELECT * FROM tenants WHERE id = :id',
                ['id' => $_SESSION['tenant_id']]
            );
        }
        return self::$currentTenant;
    }

    /**
     * Ottiene ID utente corrente
     */
    public static function getUserId(): ?int {
        return self::isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Ottiene ID tenant corrente
     */
    public static function getTenantId(): ?int {
        return self::isLoggedIn() ? (int)$_SESSION['tenant_id'] : null;
    }

    /**
     * Genera token CSRF
     */
    public static function generateCsrfToken(): string {
        $token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        $_SESSION[CSRF_TOKEN_NAME] = $token;
        return $token;
    }

    /**
     * Verifica token CSRF
     */
    public static function verifyCsrfToken(string $token): bool {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    /**
     * Richiede token CSRF valido
     */
    public static function requireCsrfToken(): void {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!self::verifyCsrfToken($token)) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit;
            } else {
                header('HTTP/1.1 403 Forbidden');
                echo 'Invalid CSRF token';
                exit;
            }
        }
    }

    /**
     * Crea nuovo utente
     */
    public static function createUser(array $data): int {
        // Validazione dati
        $required = ['tenant_id', 'username', 'email', 'password', 'full_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }

        // Verifica unicità
        if (Database::exists('users', [
            'tenant_id' => $data['tenant_id'],
            'email' => $data['email']
        ])) {
            throw new Exception('Email already exists');
        }

        if (Database::exists('users', [
            'tenant_id' => $data['tenant_id'],
            'username' => $data['username']
        ])) {
            throw new Exception('Username already exists');
        }

        // Hash password
        $data['password'] = password_hash($data['password'], PASSWORD_ALGO, PASSWORD_OPTIONS);

        // Imposta defaults
        $data['role'] = $data['role'] ?? 'user';
        $data['status'] = $data['status'] ?? 'active';

        return Database::insert('users', $data);
    }

    /**
     * Aggiorna password utente
     */
    public static function updatePassword(int $userId, string $oldPassword, string $newPassword): bool {
        $user = Database::selectOne('SELECT password FROM users WHERE id = :id', ['id' => $userId]);

        if (!$user || !password_verify($oldPassword, $user['password'])) {
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_ALGO, PASSWORD_OPTIONS);
        Database::update('users', ['password' => $hash], ['id' => $userId]);

        self::logActivity('password_change', 'user', $userId);
        return true;
    }

    /**
     * Imposta variabili sessione
     */
    private static function setSession(array $user, array $tenant): void {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenant_id'] = $tenant['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['tenant_code'] = $tenant['code'];
        $_SESSION['tenant_name'] = $tenant['name'];
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();

        // Salva sessione nel database
        self::saveSessionToDatabase();
    }

    /**
     * Salva sessione nel database
     */
    private static function saveSessionToDatabase(): void {
        $sessionData = [
            'id' => session_id(),
            'tenant_id' => $_SESSION['tenant_id'],
            'user_id' => $_SESSION['user_id'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'payload' => serialize($_SESSION),
            'last_activity' => time()
        ];

        Database::query(
            "INSERT INTO sessions (id, tenant_id, user_id, ip_address, user_agent, payload, last_activity)
             VALUES (:id, :tenant_id, :user_id, :ip_address, :user_agent, :payload, :last_activity)
             ON DUPLICATE KEY UPDATE
                payload = VALUES(payload),
                last_activity = VALUES(last_activity)",
            $sessionData
        );
    }

    /**
     * Log attività utente
     */
    public static function logActivity(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null
    ): void {
        if (!self::isLoggedIn()) {
            return;
        }

        Database::insert('activity_logs', [
            'tenant_id' => self::getTenantId(),
            'user_id' => self::getUserId(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ? json_encode($details) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    /**
     * Log tentativo di login fallito
     */
    private static function logFailedLogin(string $username, string $tenantCode, string $reason): void {
        $logFile = LOG_PATH . '/auth_' . date('Y-m-d') . '.log';
        $logMessage = sprintf(
            "[%s] Failed login - User: %s, Tenant: %s, Reason: %s, IP: %s\n",
            date('Y-m-d H:i:s'),
            $username,
            $tenantCode,
            $reason,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );

        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Pulisce sessioni vecchie
     */
    private static function cleanOldSessions(): void {
        $threshold = time() - SESSION_LIFETIME;
        Database::delete('sessions', ['last_activity' => ['<', $threshold]]);
    }

    /**
     * Verifica se richiesta è AJAX
     */
    private static function isAjaxRequest(): bool {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Genera password sicura
     */
    public static function generatePassword(int $length = 12): string {
        $sets = [
            'lowercase' => 'abcdefghijklmnopqrstuvwxyz',
            'uppercase' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'numbers' => '0123456789',
            'special' => '!@#$%^&*()_+-=[]{}|;:,.<>?'
        ];

        $password = '';
        $allChars = implode('', $sets);

        // Assicura almeno un carattere per tipo
        foreach ($sets as $set) {
            $password .= $set[random_int(0, strlen($set) - 1)];
        }

        // Riempi il resto
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Mescola
        return str_shuffle($password);
    }
}