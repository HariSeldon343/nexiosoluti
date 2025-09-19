<?php
/**
 * Configurazione V2 - Sistema di Autenticazione Avanzato
 * Supporta multi-tenant, ruoli gerarchici e login semplificato
 */

// Prevenzione accesso diretto
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// Configurazione Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'nexio_collabora_v2');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configurazione Sessioni
define('SESSION_NAME', 'NEXIO_V2_SESSID');
define('SESSION_LIFETIME', 7200); // 2 ore
define('SESSION_PATH', '/Nexiosolution/collabora/'); // Corretto per installazione in sottocartella
define('SESSION_SECURE', false); // Impostare true in produzione con HTTPS
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Lax');

// Configurazione Sicurezza
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_SPECIAL', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_UPPER', true);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minuti
define('TOKEN_EXPIRY', 3600); // 1 ora per i token di reset
define('ENABLE_2FA', false); // Abilitare per autenticazione a due fattori

// Ruoli Sistema
define('ROLE_ADMIN', 'admin');
define('ROLE_SPECIAL_USER', 'special_user');
define('ROLE_STANDARD_USER', 'standard_user');
define('ROLE_GUEST', 'guest');

// Permessi per ruolo
$ROLE_PERMISSIONS = [
    'admin' => [
        'system_management',
        'user_management',
        'tenant_management',
        'all_tenant_access',
        'settings_management',
        'backup_restore',
        'audit_logs',
        'file_management',
        'task_management',
        'calendar_management',
        'chat_management'
    ],
    'special_user' => [
        'multi_tenant_access',
        'file_management',
        'task_management',
        'calendar_management',
        'chat_management',
        'user_view',
        'report_generation'
    ],
    'standard_user' => [
        'single_tenant_access',
        'file_management',
        'task_management',
        'calendar_management',
        'chat_management'
    ],
    'guest' => [
        'view_only'
    ]
];

// Credenziali Admin Predefinite
define('DEFAULT_ADMIN_EMAIL', 'asamodeo@fortibyte.it');
define('DEFAULT_ADMIN_PASSWORD', 'Ricord@1991');
define('DEFAULT_ADMIN_NAME', 'Andrea Samodeo');
define('DEFAULT_ADMIN_PHONE', '+39 320 1234567');

// Configurazione Percorsi
define('BASE_PATH', dirname(__FILE__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('TEMP_PATH', BASE_PATH . '/temp');
define('LOG_PATH', BASE_PATH . '/logs');
define('BACKUP_PATH', BASE_PATH . '/backups');
define('ASSETS_PATH', BASE_PATH . '/assets');
define('API_PATH', BASE_PATH . '/api');

// Configurazione Upload File
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'zip']);
define('CHUNK_SIZE', 1024 * 1024); // 1 MB per chunk upload

// Configurazione API
define('API_VERSION', 'v2');
define('API_KEY_HEADER', 'X-API-Key');
define('API_RATE_LIMIT', 100); // Richieste per minuto
define('ENABLE_API_LOGGING', true);

// Configurazione Email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM_EMAIL', 'noreply@nexiosolution.com');
define('SMTP_FROM_NAME', 'Nexio Solution');

// Configurazione Sistema
define('APP_NAME', 'Nexio Solution V2');
define('APP_VERSION', '2.0.0');
define('APP_ENV', 'development'); // development | production
define('DEBUG_MODE', true); // Disabilitare in produzione
define('MAINTENANCE_MODE', false);
define('DEFAULT_LANGUAGE', 'it');
define('DEFAULT_TIMEZONE', 'Europe/Rome');

// Configurazione Cache
define('CACHE_ENABLED', false);
define('CACHE_DRIVER', 'file'); // file | redis | memcached
define('CACHE_PREFIX', 'nexio_v2_');
define('CACHE_TTL', 3600);

// Configurazione Log
define('LOG_LEVEL', 'debug'); // debug | info | warning | error
define('LOG_ROTATION', 'daily'); // daily | weekly | monthly
define('LOG_MAX_FILES', 30);

// Configurazione Tenant
define('ENABLE_MULTI_TENANT', true);
define('DEFAULT_TENANT_CODE', 'DEFAULT');
define('TENANT_ISOLATION', true); // Isolamento dati tra tenant

// URL Base
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = '/Nexiosolution/collabora';
define('BASE_URL', $protocol . '://' . $host . $path);

// Funzioni di utilità
if (!function_exists('env')) {
    function env($key, $default = null) {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('config')) {
    function config($key, $default = null) {
        return defined($key) ? constant($key) : $default;
    }
}

// Inizializzazione timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Gestione errori in base all'ambiente
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . '/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Caricamento automatico delle classi se necessario
spl_autoload_register(function ($class) {
    $file = BASE_PATH . '/includes/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Verifica requisiti sistema
function checkSystemRequirements() {
    $errors = [];

    // Verifica versione PHP
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $errors[] = 'PHP 7.4 o superiore richiesto';
    }

    // Verifica estensioni
    $required_extensions = ['mysqli', 'pdo', 'json', 'mbstring', 'openssl'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "Estensione PHP '$ext' non trovata";
        }
    }

    // Verifica directory scrivibili
    $writable_dirs = [UPLOAD_PATH, TEMP_PATH, LOG_PATH, BACKUP_PATH];
    foreach ($writable_dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_writable($dir)) {
            $errors[] = "Directory '$dir' non scrivibile";
        }
    }

    return $errors;
}

// La funzione getDbConnection() è stata spostata in includes/db.php
// per evitare duplicazioni. Usa require_once 'includes/db.php' per accedervi.

// Configurazione CORS per API
function setCorsHeaders() {
    $allowed_origins = [
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:8080'
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
    header('Access-Control-Max-Age: 3600');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}