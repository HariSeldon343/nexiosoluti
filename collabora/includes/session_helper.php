<?php declare(strict_types=1);

/**
 * Session Helper - Gestione centralizzata delle sessioni
 *
 * Questo helper risolve i problemi di redirect loop garantendo:
 * - Configurazione coerente del path dei cookie di sessione
 * - Debugging dettagliato dello stato della sessione
 * - Prevenzione dei loop di reindirizzamento
 */

namespace Collabora\Session;

class SessionHelper {

    private static $initialized = false;
    private static $debugMode = true;

    /**
     * Inizializza la sessione con configurazione corretta
     *
     * @return bool True se la sessione è stata inizializzata con successo
     */
    public static function init(): bool {
        if (self::$initialized) {
            self::debug('Session already initialized');
            return true;
        }

        // Se la sessione è già attiva, verifica che sia configurata correttamente
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$initialized = true;
            self::debug('Session already active - ID: ' . session_id());
            return true;
        }

        // Carica configurazione se non già caricata
        if (!defined('SESSION_PATH')) {
            require_once dirname(__DIR__) . '/config_v2.php';
        }

        try {
            // Configura i parametri del cookie di sessione
            $params = [
                'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 7200,
                'path' => defined('SESSION_PATH') ? SESSION_PATH : '/Nexiosolution/collabora/',
                'domain' => '',
                'secure' => defined('SESSION_SECURE') ? SESSION_SECURE : false,
                'httponly' => defined('SESSION_HTTPONLY') ? SESSION_HTTPONLY : true,
                'samesite' => defined('SESSION_SAMESITE') ? SESSION_SAMESITE : 'Lax'
            ];

            session_set_cookie_params($params);

            // Imposta nome sessione personalizzato
            if (defined('SESSION_NAME')) {
                session_name(SESSION_NAME);
            }

            // Avvia la sessione
            if (session_start()) {
                self::$initialized = true;
                self::debug('Session started successfully - ID: ' . session_id());
                self::debug('Session params: ' . json_encode($params));
                return true;
            }

        } catch (\Exception $e) {
            self::debug('Session initialization failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }

        return false;
    }

    /**
     * Verifica se l'utente è autenticato
     *
     * @return bool
     */
    public static function isAuthenticated(): bool {
        self::init();

        $authenticated = isset($_SESSION['user_v2']) || isset($_SESSION['user']);
        self::debug('Authentication check: ' . ($authenticated ? 'YES' : 'NO'));

        if ($authenticated) {
            $user = $_SESSION['user_v2'] ?? $_SESSION['user'] ?? [];
            self::debug('Authenticated user: ' . json_encode([
                'id' => $user['id'] ?? 'unknown',
                'email' => $user['email'] ?? 'unknown',
                'role' => $user['role'] ?? 'unknown'
            ]));
        }

        return $authenticated;
    }

    /**
     * Verifica se l'utente è admin
     *
     * @return bool
     */
    public static function isAdmin(): bool {
        if (!self::isAuthenticated()) {
            return false;
        }

        $user = $_SESSION['user_v2'] ?? $_SESSION['user'] ?? [];
        $isAdmin = isset($user['role']) && $user['role'] === 'admin';

        self::debug('Admin check: ' . ($isAdmin ? 'YES' : 'NO'));

        return $isAdmin;
    }

    /**
     * Ottiene i dati dell'utente corrente
     *
     * @return array|null
     */
    public static function getCurrentUser(): ?array {
        if (!self::isAuthenticated()) {
            return null;
        }

        return $_SESSION['user_v2'] ?? $_SESSION['user'] ?? null;
    }

    /**
     * Imposta i dati dell'utente nella sessione
     *
     * @param array $userData
     * @return void
     */
    public static function setUser(array $userData): void {
        self::init();

        $_SESSION['user_v2'] = $userData;

        // Mantieni compatibilità con formato legacy
        $_SESSION['user'] = $userData;

        self::debug('User data set in session: ' . json_encode([
            'id' => $userData['id'] ?? 'unknown',
            'email' => $userData['email'] ?? 'unknown',
            'role' => $userData['role'] ?? 'unknown'
        ]));
    }

    /**
     * Distrugge la sessione corrente
     *
     * @return void
     */
    public static function destroy(): void {
        self::debug('Destroying session: ' . session_id());

        // Clear session data
        $_SESSION = [];

        // Delete session cookie
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

        // Destroy session
        session_destroy();

        self::$initialized = false;
    }

    /**
     * Previene loop di reindirizzamento
     *
     * @param string $context Contesto del controllo (es. 'admin', 'login')
     * @param int $maxRedirects Numero massimo di redirect consentiti
     * @return bool True se il loop è rilevato
     */
    public static function detectRedirectLoop(string $context, int $maxRedirects = 3): bool {
        self::init();

        $key = 'redirect_count_' . $context;
        $count = $_SESSION[$key] ?? 0;

        if ($count >= $maxRedirects) {
            self::debug("Redirect loop detected in context '$context' after $count redirects", 'WARNING');
            unset($_SESSION[$key]);
            return true;
        }

        $_SESSION[$key] = $count + 1;
        self::debug("Redirect count for '$context': " . ($count + 1));

        return false;
    }

    /**
     * Resetta il contatore di redirect per un contesto
     *
     * @param string $context
     * @return void
     */
    public static function resetRedirectCount(string $context): void {
        $key = 'redirect_count_' . $context;
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
            self::debug("Redirect count reset for context '$context'");
        }
    }

    /**
     * Debug logging
     *
     * @param string $message
     * @param string $level
     * @return void
     */
    private static function debug(string $message, string $level = 'INFO'): void {
        if (!self::$debugMode) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'unknown';

        error_log("[SESSION_HELPER][$timestamp][$level][$caller] $message");
    }

    /**
     * Ottiene informazioni di debug sulla sessione
     *
     * @return array
     */
    public static function getDebugInfo(): array {
        self::init();

        return [
            'session_id' => session_id(),
            'session_name' => session_name(),
            'session_status' => session_status(),
            'session_params' => session_get_cookie_params(),
            'is_authenticated' => self::isAuthenticated(),
            'is_admin' => self::isAdmin(),
            'user_data' => self::getCurrentUser(),
            'session_data' => $_SESSION
        ];
    }
}