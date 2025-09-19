# Nexiosolution Collabora - Guida alla Configurazione

## Panoramica

Il file `config.php` contiene tutte le impostazioni dell'applicazione. Questa guida spiega ogni parametro e come personalizzarlo.

## Configurazioni Database

### Connessione Base
```php
define('DB_HOST', 'localhost');     // Host del database
define('DB_PORT', '3306');          // Porta MySQL (default: 3306)
define('DB_NAME', 'nexio_collabora'); // Nome database
define('DB_USER', 'root');          // Username MySQL
define('DB_PASS', '');              // Password (vuota per XAMPP default)
```

### Pool di Connessioni
```php
define('DB_PERSISTENT', false);      // Usa connessioni persistenti
define('DB_CHARSET', 'utf8mb4');    // Set di caratteri
define('DB_COLLATION', 'utf8mb4_unicode_ci');
```

## Configurazioni Applicazione

### Informazioni Base
```php
define('SITE_NAME', 'Nexiosolution Collabora');
define('SITE_URL', 'http://localhost/Nexiosolution/collabora');
define('ADMIN_EMAIL', 'admin@nexiosolution.local');
define('VERSION', '1.0.0');
```

### Percorsi Directory
```php
define('ROOT_DIR', __DIR__);
define('UPLOAD_DIR', ROOT_DIR . '/uploads');
define('LOG_DIR', ROOT_DIR . '/logs');
define('CACHE_DIR', ROOT_DIR . '/assets/cache');
define('TEMP_DIR', sys_get_temp_dir());
```

## Configurazioni di Sicurezza

### Chiavi di Crittografia
```php
// IMPORTANTE: Genera chiavi univoche per produzione!
define('SECRET_KEY', 'cambia-questa-chiave-32-caratteri');
define('JWT_SECRET', 'cambia-questa-chiave-jwt-sicura');
define('ENCRYPTION_KEY', 'chiave-16-chars');

// Genera chiavi sicure con:
// echo bin2hex(random_bytes(32));
```

### Sessioni
```php
define('SESSION_LIFETIME', 3600);    // Durata in secondi (1 ora)
define('SESSION_NAME', 'NEXIO_SESS');
define('SESSION_SECURE', false);     // true per HTTPS
define('SESSION_HTTPONLY', true);    // Previene accesso JS
define('SESSION_SAMESITE', 'Lax');   // CSRF protection
```

### Password Policy
```php
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_SPECIAL', false);
define('PASSWORD_BCRYPT_COST', 12);
```

## Configurazioni Upload

### Limiti e Restrizioni
```php
define('MAX_UPLOAD_SIZE', 104857600);  // 100MB in bytes
define('MAX_FILES_PER_UPLOAD', 10);
define('CHUNK_SIZE', 1048576);          // 1MB per chunk upload

// Estensioni permesse
define('ALLOWED_EXTENSIONS', [
    // Documenti
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'odt', 'ods', 'odp', 'txt', 'rtf', 'csv',

    // Immagini
    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp',

    // Archivi
    'zip', 'rar', '7z', 'tar', 'gz',

    // Media
    'mp3', 'mp4', 'avi', 'mov', 'wmv'
]);

// MIME types permessi
define('ALLOWED_MIMES', [
    'application/pdf',
    'application/msword',
    'application/vnd.ms-excel',
    'image/jpeg',
    'image/png',
    'text/plain'
]);
```

### Storage
```php
define('STORAGE_METHOD', 'local');     // local, s3, ftp
define('STORAGE_PATH', UPLOAD_DIR);
define('STORAGE_URL', SITE_URL . '/uploads');

// Quota utenti (in bytes)
define('DEFAULT_USER_QUOTA', 1073741824);  // 1GB
define('ADMIN_USER_QUOTA', 10737418240);   // 10GB
```

## Configurazioni Email

### SMTP Settings
```php
define('MAIL_DRIVER', 'smtp');         // smtp, mail, sendmail
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 25);
define('SMTP_AUTH', false);
define('SMTP_SECURE', '');              // tls, ssl, o vuoto
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM_EMAIL', 'noreply@nexiosolution.local');
define('SMTP_FROM_NAME', 'Nexiosolution Collabora');
```

### Template Email
```php
define('EMAIL_TEMPLATES', [
    'welcome' => 'emails/welcome.html',
    'reset_password' => 'emails/reset.html',
    'file_shared' => 'emails/shared.html'
]);
```

## Configurazioni API

### Rate Limiting
```php
define('API_RATE_LIMIT', 100);          // Richieste per minuto
define('API_KEY_LENGTH', 32);
define('API_ENABLE_CORS', true);
define('API_ALLOWED_ORIGINS', [
    'http://localhost',
    'http://localhost:3000'
]);
```

### Webhook
```php
define('WEBHOOKS_ENABLED', false);
define('WEBHOOK_SECRET', 'webhook-secret-key');
define('WEBHOOK_TIMEOUT', 5);           // Secondi
```

## Configurazioni WebDAV

```php
define('WEBDAV_ENABLED', true);
define('WEBDAV_PATH', '/webdav');
define('WEBDAV_AUTH_TYPE', 'basic');   // basic, digest
define('WEBDAV_REALM', 'Nexiosolution Files');
```

## Configurazioni Cache

```php
define('CACHE_ENABLED', true);
define('CACHE_DRIVER', 'file');        // file, redis, memcached
define('CACHE_PREFIX', 'nexio_');
define('CACHE_TTL', 3600);              // Time to live in secondi

// Redis (se utilizzato)
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', '');
```

## Configurazioni Log

```php
define('LOG_ERRORS', true);
define('LOG_LEVEL', 'info');           // debug, info, warning, error
define('LOG_ROTATION', 'daily');       // daily, weekly, monthly
define('LOG_MAX_FILES', 30);            // Mantieni ultimi 30 file

// Tipi di log
define('LOG_AUTH', true);               // Log autenticazioni
define('LOG_UPLOADS', true);            // Log upload file
define('LOG_API', true);                // Log chiamate API
define('LOG_ERRORS_TO_EMAIL', false);  // Invia errori critici via email
```

## Configurazioni Debug

```php
define('DEBUG_MODE', false);            // true solo in sviluppo!
define('DISPLAY_ERRORS', false);
define('ERROR_REPORTING', E_ALL);
define('SQL_DEBUG', false);             // Log query SQL
```

## Configurazioni Internazionalizzazione

```php
define('DEFAULT_LANGUAGE', 'it');      // it, en, es, fr, de
define('DEFAULT_TIMEZONE', 'Europe/Rome');
define('DATE_FORMAT', 'd/m/Y');
define('TIME_FORMAT', 'H:i');
define('DATETIME_FORMAT', 'd/m/Y H:i');
```

## Configurazioni UI/UX

```php
define('THEME', 'default');             // default, dark, light
define('ITEMS_PER_PAGE', 20);
define('MAX_BREADCRUMB_ITEMS', 5);
define('ENABLE_ANIMATIONS', true);
define('SHOW_FILE_ICONS', true);
define('PREVIEW_MAX_SIZE', 5242880);   // 5MB max per preview
```

## Configurazioni Avanzate

### Collabora Online / OnlyOffice
```php
define('OFFICE_ENABLED', false);
define('OFFICE_TYPE', 'collabora');    // collabora, onlyoffice
define('OFFICE_URL', 'https://collabora.example.com');
define('OFFICE_SECRET', 'office-secret-key');
```

### Antivirus
```php
define('AV_ENABLED', false);
define('AV_ENGINE', 'clamav');         // clamav, sophos
define('AV_PATH', '/usr/bin/clamscan');
define('AV_QUARANTINE', UPLOAD_DIR . '/quarantine');
```

### OCR (Optical Character Recognition)
```php
define('OCR_ENABLED', false);
define('OCR_ENGINE', 'tesseract');
define('OCR_LANGUAGES', ['ita', 'eng']);
```

### Full-Text Search
```php
define('SEARCH_ENGINE', 'database');    // database, elasticsearch, solr
define('ELASTICSEARCH_HOST', 'localhost:9200');
define('SEARCH_MIN_LENGTH', 3);
```

## Configurazioni Performance

```php
// Database
define('DB_QUERY_CACHE', true);
define('DB_SLOW_QUERY_LOG', true);
define('DB_SLOW_QUERY_TIME', 1);       // Secondi

// File System
define('USE_SYMLINKS', false);
define('PARALLEL_UPLOADS', 3);
define('COMPRESSION_ENABLED', true);

// Memory
define('MEMORY_LIMIT', '256M');
define('MAX_EXECUTION_TIME', 300);
```

## Configurazioni Backup

```php
define('BACKUP_ENABLED', true);
define('BACKUP_PATH', ROOT_DIR . '/backups');
define('BACKUP_RETENTION_DAYS', 30);
define('BACKUP_COMPRESSION', true);
define('BACKUP_ENCRYPT', false);
define('BACKUP_SCHEDULE', '0 2 * * *'); // Cron format
```

## Esempio di Configurazione Completa

### Sviluppo (Local)
```php
<?php
// config.development.php
define('DEBUG_MODE', true);
define('DB_HOST', 'localhost');
define('DB_PASS', '');
define('SITE_URL', 'http://localhost/collabora');
define('SMTP_HOST', 'localhost');
define('LOG_LEVEL', 'debug');
```

### Produzione
```php
<?php
// config.production.php
define('DEBUG_MODE', false);
define('DB_HOST', 'db.example.com');
define('DB_PASS', 'strong-password-here');
define('SITE_URL', 'https://files.example.com');
define('SMTP_HOST', 'smtp.gmail.com');
define('LOG_LEVEL', 'error');
define('SESSION_SECURE', true);
```

## Validazione Configurazione

Esegui questo comando per validare la configurazione:
```bash
php validate-config.php
```

Output atteso:
```
✅ Database connection: OK
✅ Directory permissions: OK
✅ PHP extensions: OK
✅ Email configuration: OK
✅ Security settings: OK
```

## Best Practices

1. **Mai committare `config.php` con credenziali reali**
2. **Usa variabili d'ambiente per informazioni sensibili**
3. **Ruota le chiavi di sicurezza regolarmente**
4. **Mantieni configurazioni separate per dev/staging/prod**
5. **Documenta ogni modifica alla configurazione**
6. **Fai backup prima di modificare configurazioni**
7. **Testa le modifiche in ambiente di sviluppo**
8. **Monitora i log dopo modifiche**

## Troubleshooting

### Problema: "Config file not found"
```bash
cp config.example.php config.php
chmod 644 config.php
```

### Problema: "Invalid configuration"
```php
// Abilita debug temporaneamente
define('DEBUG_MODE', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Problema: "Permission denied"
```bash
# Linux/Mac
chmod 755 /path/to/collabora
chmod 777 uploads/ logs/ cache/

# Windows
icacls config.php /grant Everyone:R
```

## Supporto

Per assistenza sulla configurazione:
- Email: config@nexiosolution.com
- Docs: https://docs.nexiosolution.com/config
- Forum: https://forum.nexiosolution.com