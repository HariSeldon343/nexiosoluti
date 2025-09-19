<?php
declare(strict_types=1);

/**
 * Configurazione principale del sistema
 * Multi-tenant file management system
 */

// Error reporting per ambiente
$serverName = $_SERVER['SERVER_NAME'] ?? php_sapi_name();
if ($serverName === 'localhost' || $serverName === '127.0.0.1' || $serverName === 'cli') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    define('ENVIRONMENT', 'development');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    define('ENVIRONMENT', 'production');
}

// Configurazione database
define('DB_HOST', 'localhost');
define('DB_NAME', 'collabora_files');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configurazione applicazione
define('APP_NAME', 'Nexio File Manager');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/Nexiosolution/collabora');
define('APP_ROOT', __DIR__);
define('UPLOAD_PATH', APP_ROOT . '/uploads');
define('LOG_PATH', APP_ROOT . '/logs');
define('MAX_FILE_SIZE', 104857600); // 100MB in bytes
define('CHUNK_SIZE', 1048576); // 1MB chunks per upload

// Configurazione sessione
define('SESSION_NAME', 'nexio_session');
define('SESSION_LIFETIME', 7200); // 2 ore
define('SESSION_PATH', '/');
define('SESSION_SECURE', false); // true in produzione con HTTPS
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Strict');

// Configurazione sicurezza
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LENGTH', 32);
define('PASSWORD_ALGO', PASSWORD_ARGON2ID);
define('PASSWORD_OPTIONS', [
    'memory_cost' => 65536,
    'time_cost' => 4,
    'threads' => 3
]);

// Configurazione file
define('ALLOWED_EXTENSIONS', [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'txt', 'csv', 'rtf', 'odt', 'ods', 'odp',
    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp',
    'mp3', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm',
    'zip', 'rar', '7z', 'tar', 'gz',
    'xml', 'json', 'html', 'css', 'js', 'php', 'sql'
]);

define('MIME_TYPES', [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt' => 'text/plain',
    'csv' => 'text/csv',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'mp3' => 'audio/mpeg',
    'mp4' => 'video/mp4',
    'zip' => 'application/zip',
    'json' => 'application/json',
    'xml' => 'application/xml'
]);

// Configurazione pulizia
define('TRASH_RETENTION_DAYS', 30);
define('LOG_RETENTION_DAYS', 90);
define('SESSION_CLEANUP_PROBABILITY', 1); // 1% probabilità di pulizia

// Configurazione limiti
define('MAX_FILES_PER_UPLOAD', 100);
define('MAX_ZIP_SIZE', 524288000); // 500MB per ZIP
define('RATE_LIMIT_REQUESTS', 100); // richieste per minuto
define('RATE_LIMIT_WINDOW', 60); // secondi

// Timezone
date_default_timezone_set('Europe/Rome');

// Autoloader per le classi
spl_autoload_register(function ($class) {
    $file = APP_ROOT . '/includes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Funzioni helper globali
function sanitize_input(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generate_uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function format_bytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function clean_filename(string $filename): string {
    $filename = preg_replace('/[^\w\s\d\-_~,;\[\]\(\).]/', '', $filename);
    $filename = preg_replace('/\.+/', '.', $filename);
    return trim($filename, '.');
}

function get_file_icon(string $extension): string {
    $icons = [
        'pdf' => 'file-pdf',
        'doc' => 'file-word', 'docx' => 'file-word',
        'xls' => 'file-excel', 'xlsx' => 'file-excel',
        'ppt' => 'file-powerpoint', 'pptx' => 'file-powerpoint',
        'txt' => 'file-text',
        'jpg' => 'file-image', 'jpeg' => 'file-image', 'png' => 'file-image', 'gif' => 'file-image',
        'mp3' => 'file-audio', 'mp4' => 'file-video',
        'zip' => 'file-archive', 'rar' => 'file-archive',
        'default' => 'file'
    ];
    return $icons[$extension] ?? $icons['default'];
}

// Headers di sicurezza
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    if (ENVIRONMENT === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}