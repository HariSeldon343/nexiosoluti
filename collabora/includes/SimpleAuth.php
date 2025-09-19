<?php
/**
 * Simple Authentication Class for Nexio Collabora
 * Minimal implementation for login functionality
 */

// Custom exceptions for specific error types
class AuthException extends Exception {
    protected $errorCode = 'auth_error';
    protected $httpCode = 500;
    protected $fields = [];

    public function getErrorCode() {
        return $this->errorCode;
    }

    public function getHttpCode() {
        return $this->httpCode;
    }

    public function getFields() {
        return $this->fields;
    }
}

class InvalidCredentialsException extends AuthException {
    protected $errorCode = 'invalid_credentials';
    protected $httpCode = 401;
}

class MissingFieldsException extends AuthException {
    protected $errorCode = 'missing_fields';
    protected $httpCode = 400;

    public function __construct($message = '', $fields = [], $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->fields = $fields;
    }
}

class DatabaseException extends AuthException {
    protected $errorCode = 'database_error';
    protected $httpCode = 500;
}

class AccountInactiveException extends AuthException {
    protected $errorCode = 'account_inactive';
    protected $httpCode = 403;
}

class SimpleAuth {
    private $db;

    public function __construct() {
        // Start session if not started with proper configuration
        if (session_status() === PHP_SESSION_NONE) {
            // Load config to get session settings
            if (!defined('SESSION_PATH')) {
                require_once __DIR__ . '/../config_v2.php';
            }

            // Configure session parameters for subfolder installation
            session_set_cookie_params([
                'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 7200,
                'path' => defined('SESSION_PATH') ? SESSION_PATH : '/Nexiosolution/collabora/',
                'domain' => '',
                'secure' => defined('SESSION_SECURE') ? SESSION_SECURE : false,
                'httponly' => defined('SESSION_HTTPONLY') ? SESSION_HTTPONLY : true,
                'samesite' => defined('SESSION_SAMESITE') ? SESSION_SAMESITE : 'Lax'
            ]);

            // Use custom session name if defined
            if (defined('SESSION_NAME')) {
                session_name(SESSION_NAME);
            }

            session_start();

            // Log session initialization
            error_log('[SimpleAuth] Session initialized - ID: ' . session_id() . ', Path: ' . (defined('SESSION_PATH') ? SESSION_PATH : 'default'));
        }

        // Get database connection
        $this->db = $this->getDbConnection();
    }

    private function getDbConnection() {
        try {
            // Include config if not already defined
            if (!defined('DB_HOST')) {
                require_once __DIR__ . '/../config_v2.php';
            }

            // Check if db.php exists and has getDbConnection function
            if (file_exists(__DIR__ . '/db.php')) {
                require_once __DIR__ . '/db.php';
                if (function_exists('getDbConnection')) {
                    return getDbConnection();
                }
            }

            // Fallback to direct connection
            $dsn = 'mysql:host=' . (defined('DB_HOST') ? DB_HOST : 'localhost') .
                   ';dbname=' . (defined('DB_NAME') ? DB_NAME : 'nexio_collabora_v2') .
                   ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ];

            $pdo = new PDO(
                $dsn,
                defined('DB_USER') ? DB_USER : 'root',
                defined('DB_PASS') ? DB_PASS : '',
                $options
            );

            // Test connection
            $pdo->query('SELECT 1');

            return $pdo;
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new DatabaseException('Database connection failed');
        }
    }

    public function login($email, $password) {
        try {
            // Validate input
            $missingFields = [];
            if (empty($email)) {
                $missingFields[] = 'email';
            }
            if (empty($password)) {
                $missingFields[] = 'password';
            }

            if (!empty($missingFields)) {
                throw new MissingFieldsException(
                    'Email e password sono obbligatori',
                    $missingFields
                );
            }

            // Log attempt (without password)
            error_log('Login attempt for email: ' . $email);

            // Get user from database
            $stmt = $this->db->prepare("
                SELECT id, email, password, first_name, last_name, role, is_system_admin, status
                FROM users
                WHERE email = :email
                AND (deleted_at IS NULL OR deleted_at = '')
            ");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Log for debugging but don't reveal to client
                error_log('User not found for email: ' . $email);
                throw new InvalidCredentialsException('Credenziali non valide');
            }

            // Check if account is active
            if ($user['status'] !== 'active') {
                error_log('Account inactive for email: ' . $email);
                throw new AccountInactiveException('Account non attivo');
            }

            // Verify password
            if (!password_verify($password, $user['password'])) {
                error_log('Invalid password for email: ' . $email);
                throw new InvalidCredentialsException('Credenziali non valide');
            }

            error_log('Login successful for user: ' . $user['id'] . ' (' . $email . ')');

            // Update last login
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $stmt->execute(['id' => $user['id']]);

            // Get user's tenants - check for different table names
            $tenantQuery = "
                SELECT t.id, t.code, t.name
                FROM tenants t
                LEFT JOIN user_tenant_associations uta ON t.id = uta.tenant_id
                WHERE (uta.user_id = :user_id OR :is_admin = 1)
                AND (t.status = 'active' OR t.status IS NULL)
                GROUP BY t.id
            ";

            // Try alternate table name if first fails
            try {
                $stmt = $this->db->prepare($tenantQuery);
                $stmt->execute([
                    'user_id' => $user['id'],
                    'is_admin' => ($user['role'] === 'admin' || $user['is_system_admin'] == 1) ? 1 : 0
                ]);
                $tenants = $stmt->fetchAll();
            } catch (PDOException $e) {
                // Try simpler query if join table doesn't exist
                try {
                    $stmt = $this->db->prepare("SELECT id, code, name FROM tenants WHERE status = 'active' OR status IS NULL");
                    $stmt->execute();
                    $tenants = $stmt->fetchAll();
                } catch (PDOException $e2) {
                    $tenants = [];
                }
            }

            // If no tenants, get default tenant
            if (empty($tenants)) {
                $stmt = $this->db->prepare("SELECT id, code, name FROM tenants WHERE code = 'DEFAULT'");
                $stmt->execute();
                $defaultTenant = $stmt->fetch();
                if ($defaultTenant) {
                    $tenants = [$defaultTenant];
                }
            }

            // Set up session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['is_admin'] = $user['is_system_admin'] == 1;
            $_SESSION['current_tenant_id'] = !empty($tenants) ? $tenants[0]['id'] : null;
            $_SESSION['user_v2'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'role' => $user['role']
            ];

            // Return success response
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                    'role' => $user['role'],
                    'is_admin' => $user['is_system_admin'] == 1
                ],
                'tenants' => $tenants,
                'current_tenant_id' => $_SESSION['current_tenant_id'],
                'session_id' => session_id()
            ];

        } catch (AuthException $e) {
            // Re-throw custom exceptions
            throw $e;
        } catch (PDOException $e) {
            error_log('Database error during login: ' . $e->getMessage());
            throw new DatabaseException('Database error during login');
        } catch (Exception $e) {
            error_log('Unexpected error during login: ' . $e->getMessage());
            throw new AuthException('Unexpected error during login');
        }
    }

    public function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Logout effettuato con successo'];
    }

    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        return $_SESSION['user_v2'] ?? null;
    }

    public function switchTenant($tenantId) {
        if (!$this->isAuthenticated()) {
            throw new InvalidCredentialsException('Non autenticato');
        }

        // Admin can switch to any tenant
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
            $_SESSION['current_tenant_id'] = $tenantId;
            return true;
        }

        // Verify user has access to this tenant - try both table names
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM user_tenant_associations
                WHERE user_id = :user_id
                AND tenant_id = :tenant_id
            ");
            $stmt->execute([
                'user_id' => $_SESSION['user_id'],
                'tenant_id' => $tenantId
            ]);
            $result = $stmt->fetch();
        } catch (PDOException $e) {
            // Try alternate table name
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count
                    FROM user_tenants
                    WHERE user_id = :user_id
                    AND tenant_id = :tenant_id
                ");
                $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'tenant_id' => $tenantId
                ]);
                $result = $stmt->fetch();
            } catch (PDOException $e2) {
                // If both fail, deny access for non-admins
                throw new AuthException('Accesso al tenant non autorizzato');
            }
        }

        if ($result['count'] == 0) {
            throw new AuthException('Accesso al tenant non autorizzato');
        }

        $_SESSION['current_tenant_id'] = $tenantId;
        return true;
    }

    public function generateCSRFToken() {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token;
    }
}