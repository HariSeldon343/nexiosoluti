<?php declare(strict_types=1);

/**
 * Setup script for Authentication System V2
 * Inizializza il database e crea l'utente admin predefinito
 *
 * @author Nexiosolution
 * @version 2.0.0
 * @since 2025-01-17
 */

// Configurazione
$config = [
    'db_host' => 'localhost',
    'db_name' => 'collabora_files',
    'db_user' => 'root',
    'db_pass' => '',
    'admin_email' => 'asamodeo@fortibyte.it',
    'admin_password' => 'Ricord@1991'
];

// Colori per output console
$colors = [
    'reset' => "\033[0m",
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m"
];

function output($message, $color = 'reset') {
    global $colors;
    echo $colors[$color] . $message . $colors['reset'] . PHP_EOL;
}

output("=====================================", 'blue');
output("Setup Authentication System V2", 'blue');
output("=====================================", 'blue');
output("");

try {
    // Connessione al database
    output("Connessione al database...", 'yellow');
    $dsn = "mysql:host={$config['db_host']};charset=utf8mb4";
    $db = new PDO($dsn, $config['db_user'], $config['db_pass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    output("✓ Connesso al server MySQL", 'green');

    // Crea database se non esiste
    output("Creazione database se non esiste...", 'yellow');
    $db->exec("CREATE DATABASE IF NOT EXISTS `{$config['db_name']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->exec("USE `{$config['db_name']}`");
    output("✓ Database '{$config['db_name']}' pronto", 'green');

    // Esegui migration SQL
    output("Esecuzione migration database...", 'yellow');
    $migrationFile = __DIR__ . '/database/migration_v2_auth.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("File di migrazione non trovato: $migrationFile");
    }

    $sql = file_get_contents($migrationFile);

    // Rimuovi commenti e dividi per statement
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    // Esegui statements uno alla volta
    $statements = array_filter(explode(';', $sql));
    $totalStatements = count($statements);
    $executedStatements = 0;

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $db->exec($statement);
                $executedStatements++;
            } catch (PDOException $e) {
                // Ignora errori per oggetti già esistenti
                if (!str_contains($e->getMessage(), 'already exists') &&
                    !str_contains($e->getMessage(), 'Duplicate')) {
                    throw $e;
                }
            }
        }
    }

    output("✓ Migration eseguita ($executedStatements/$totalStatements statements)", 'green');

    // Crea/Aggiorna utente admin predefinito
    output("Creazione utente admin predefinito...", 'yellow');

    // Hash password
    $hashedPassword = password_hash($config['admin_password'], PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);

    // Verifica se l'utente esiste già
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $config['admin_email']]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        // Aggiorna utente esistente
        $stmt = $db->prepare("
            UPDATE users SET
                password = :password,
                role = 'admin',
                is_system_admin = TRUE,
                status = 'active',
                failed_login_attempts = 0,
                locked_until = NULL,
                email_verified_at = NOW(),
                updated_at = NOW()
            WHERE email = :email
        ");

        $stmt->execute([
            'password' => $hashedPassword,
            'email' => $config['admin_email']
        ]);

        output("✓ Utente admin aggiornato: {$config['admin_email']}", 'green');
    } else {
        // Crea nuovo utente
        $stmt = $db->prepare("
            INSERT INTO users (
                email, password, first_name, last_name,
                role, is_system_admin, status,
                email_verified_at, created_at
            ) VALUES (
                :email, :password, 'Admin', 'System',
                'admin', TRUE, 'active',
                NOW(), NOW()
            )
        ");

        $stmt->execute([
            'email' => $config['admin_email'],
            'password' => $hashedPassword
        ]);

        output("✓ Utente admin creato: {$config['admin_email']}", 'green');
    }

    // Crea tenant di default se non esiste
    output("Creazione tenant di default...", 'yellow');

    $stmt = $db->query("SELECT COUNT(*) FROM tenants WHERE deleted_at IS NULL");
    $tenantCount = $stmt->fetchColumn();

    if ($tenantCount == 0) {
        $stmt = $db->prepare("
            INSERT INTO tenants (
                name, status, settings,
                storage_quota_gb, subscription_tier,
                created_at
            ) VALUES (
                'Default Tenant', 'active', :settings,
                100, 'free',
                NOW()
            )
        ");

        $defaultSettings = json_encode([
            'locale' => 'it_IT',
            'timezone' => 'Europe/Rome',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i'
        ]);

        $stmt->execute(['settings' => $defaultSettings]);
        output("✓ Tenant di default creato", 'green');
    } else {
        output("✓ Tenant già esistenti: $tenantCount", 'green');
    }

    // Pulisci vecchie sessioni
    output("Pulizia sessioni obsolete...", 'yellow');
    $db->exec("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    output("✓ Sessioni obsolete rimosse", 'green');

    // Verifica tabelle create
    output("\nVerifica tabelle create:", 'yellow');
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $requiredTables = [
        'tenants',
        'users',
        'user_tenant_associations',
        'user_sessions',
        'activity_logs',
        'permission_sets'
    ];

    foreach ($requiredTables as $table) {
        if (in_array($table, $tables)) {
            output("  ✓ $table", 'green');
        } else {
            output("  ✗ $table", 'red');
        }
    }

    // Test login
    output("\nTest autenticazione...", 'yellow');
    require_once __DIR__ . '/includes/auth_v2.php';

    $auth = new Collabora\Auth\AuthenticationV2();
    $result = $auth->login($config['admin_email'], $config['admin_password']);

    if ($result['success']) {
        output("✓ Login admin funzionante", 'green');
        output("  - User ID: " . $result['user']['id'], 'blue');
        output("  - Role: " . $result['user']['role'], 'blue');
        output("  - Is Admin: " . ($result['user']['is_system_admin'] ? 'Yes' : 'No'), 'blue');

        // Logout
        $auth->logout();
    } else {
        output("✗ Login admin fallito", 'red');
    }

    output("\n=====================================", 'green');
    output("Setup completato con successo!", 'green');
    output("=====================================", 'green');
    output("\nCredenziali Admin:");
    output("Email: {$config['admin_email']}", 'blue');
    output("Password: {$config['admin_password']}", 'blue');
    output("\nEndpoint API disponibili:");
    output("- POST /api/auth_v2/login - Login", 'blue');
    output("- POST /api/auth_v2/logout - Logout", 'blue');
    output("- GET  /api/auth_v2/me - Utente corrente", 'blue');
    output("- GET  /api/auth_v2/tenants - Tenant disponibili", 'blue');
    output("- POST /api/auth_v2/switch-tenant - Cambia tenant", 'blue');
    output("- GET  /api/users - Lista utenti (admin)", 'blue');
    output("- POST /api/users - Crea utente (admin)", 'blue');
    output("- GET  /api/tenants - Lista tenant", 'blue');
    output("- POST /api/tenants - Crea tenant (admin)", 'blue');

} catch (Exception $e) {
    output("\n✗ Errore: " . $e->getMessage(), 'red');
    output("\nStack trace:", 'yellow');
    output($e->getTraceAsString());
    exit(1);
}

// Se eseguito da browser, mostra output HTML
if (php_sapi_name() !== 'cli') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Setup Auth V2</title>
        <style>
            body {
                font-family: monospace;
                background: #1e1e1e;
                color: #d4d4d4;
                padding: 20px;
                white-space: pre-wrap;
            }
            .red { color: #f48771; }
            .green { color: #89d185; }
            .yellow { color: #e9e97a; }
            .blue { color: #66a3e0; }
        </style>
    </head>
    <body>
        <script>
            // Converti colori ANSI in classi CSS
            const output = document.body.innerHTML;
            const converted = output
                .replace(/\033\[31m(.*?)\033\[0m/g, '<span class="red">$1</span>')
                .replace(/\033\[32m(.*?)\033\[0m/g, '<span class="green">$1</span>')
                .replace(/\033\[33m(.*?)\033\[0m/g, '<span class="yellow">$1</span>')
                .replace(/\033\[34m(.*?)\033\[0m/g, '<span class="blue">$1</span>')
                .replace(/\033\[0m/g, '');
            document.body.innerHTML = converted;
        </script>
    </body>
    </html>
    <?php
}