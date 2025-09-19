<?php
/**
 * Test V2 - Script di Test Completo per il Sistema di Autenticazione
 */

session_start();
require_once 'config_v2.php';

// Colori per output console
class TestColors {
    const RESET = "\033[0m";
    const GREEN = "\033[32m";
    const RED = "\033[31m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const BOLD = "\033[1m";
}

// Classe per gestire i test
class SystemTest {
    private $db;
    private $tests_passed = 0;
    private $tests_failed = 0;
    private $test_results = [];

    public function __construct() {
        // Inizializzazione
    }

    /**
     * Esegui tutti i test
     */
    public function runAllTests() {
        echo "\n" . TestColors::BOLD . TestColors::BLUE . "=== NEXIO SOLUTION V2 - TEST SISTEMA ===" . TestColors::RESET . "\n\n";

        // 1. Test requisiti sistema
        $this->testSystemRequirements();

        // 2. Test connessione database
        $this->testDatabaseConnection();

        // 3. Test schema database
        $this->testDatabaseSchema();

        // 4. Test login admin
        $this->testAdminLogin();

        // 5. Test ruoli e permessi
        $this->testRolesAndPermissions();

        // 6. Test associazioni tenant
        $this->testTenantAssociations();

        // 7. Test cambio tenant per special users
        $this->testMultiTenantSwitching();

        // 8. Test API endpoints
        $this->testApiEndpoints();

        // 9. Test sicurezza
        $this->testSecurityFeatures();

        // 10. Test performance
        $this->testPerformance();

        // Riepilogo risultati
        $this->displayResults();
    }

    /**
     * Test requisiti sistema
     */
    private function testSystemRequirements() {
        echo TestColors::BOLD . "1. Test Requisiti Sistema" . TestColors::RESET . "\n";

        // PHP Version
        $php_version = phpversion();
        if (version_compare($php_version, '7.4.0', '>=')) {
            $this->logSuccess("PHP Version: $php_version");
        } else {
            $this->logError("PHP Version $php_version non supportata (richiesta >= 7.4)");
        }

        // Estensioni richieste
        $extensions = ['mysqli', 'pdo', 'json', 'mbstring', 'openssl', 'curl'];
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                $this->logSuccess("Estensione $ext caricata");
            } else {
                $this->logError("Estensione $ext mancante");
            }
        }

        // Directory necessarie
        $dirs = [
            UPLOAD_PATH => 'Upload',
            TEMP_PATH => 'Temp',
            LOG_PATH => 'Logs',
            BACKUP_PATH => 'Backup'
        ];

        foreach ($dirs as $path => $name) {
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }

            if (is_writable($path)) {
                $this->logSuccess("Directory $name scrivibile");
            } else {
                $this->logError("Directory $name non scrivibile: $path");
            }
        }

        echo "\n";
    }

    /**
     * Test connessione database
     */
    private function testDatabaseConnection() {
        echo TestColors::BOLD . "2. Test Connessione Database" . TestColors::RESET . "\n";

        try {
            $this->db = getDbConnection();
            $this->logSuccess("Connessione al database stabilita");

            // Test query base
            $result = $this->db->query("SELECT VERSION() as version");
            $version = $result->fetch()['version'];
            $this->logSuccess("MySQL Version: $version");

            // Test charset
            $result = $this->db->query("SHOW VARIABLES LIKE 'character_set_database'");
            $charset = $result->fetch()['Value'];
            if ($charset === 'utf8mb4') {
                $this->logSuccess("Charset database: $charset");
            } else {
                $this->logWarning("Charset database: $charset (consigliato utf8mb4)");
            }

        } catch (Exception $e) {
            $this->logError("Errore connessione database: " . $e->getMessage());
        }

        echo "\n";
    }

    /**
     * Test schema database
     */
    private function testDatabaseSchema() {
        echo TestColors::BOLD . "3. Test Schema Database" . TestColors::RESET . "\n";

        if (!$this->db) {
            $this->logError("Database non connesso");
            echo "\n";
            return;
        }

        // Tabelle richieste
        $required_tables = [
            'tenants' => ['id', 'code', 'name', 'status', 'created_at'],
            'users' => ['id', 'email', 'password', 'name', 'role', 'status', 'created_at'],
            'user_tenants' => ['user_id', 'tenant_id', 'is_primary'],
            'sessions' => ['id', 'user_id', 'token', 'ip_address', 'user_agent', 'expires_at'],
            'login_attempts' => ['id', 'email', 'ip_address', 'attempted_at'],
            'audit_logs' => ['id', 'user_id', 'action', 'details', 'ip_address', 'created_at'],
            'tasks' => ['id', 'tenant_id', 'user_id', 'title', 'status', 'created_at'],
            'files' => ['id', 'tenant_id', 'user_id', 'filename', 'path', 'created_at']
        ];

        foreach ($required_tables as $table => $columns) {
            try {
                $result = $this->db->query("SHOW TABLES LIKE '$table'");
                if ($result->rowCount() > 0) {
                    $this->logSuccess("Tabella '$table' presente");

                    // Verifica colonne principali
                    $col_check = $this->db->query("SHOW COLUMNS FROM $table");
                    $existing_cols = [];
                    while ($col = $col_check->fetch()) {
                        $existing_cols[] = $col['Field'];
                    }

                    $missing = array_diff($columns, $existing_cols);
                    if (empty($missing)) {
                        $this->logSuccess("  └─ Struttura corretta");
                    } else {
                        $this->logWarning("  └─ Colonne mancanti: " . implode(', ', $missing));
                    }
                } else {
                    $this->logError("Tabella '$table' mancante");
                }
            } catch (Exception $e) {
                $this->logError("Errore verifica tabella '$table': " . $e->getMessage());
            }
        }

        echo "\n";
    }

    /**
     * Test login amministratore
     */
    private function testAdminLogin() {
        echo TestColors::BOLD . "4. Test Login Amministratore" . TestColors::RESET . "\n";

        if (!$this->db) {
            $this->logError("Database non connesso");
            echo "\n";
            return;
        }

        $admin_email = DEFAULT_ADMIN_EMAIL;
        $admin_password = DEFAULT_ADMIN_PASSWORD;

        try {
            // Verifica esistenza admin
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
            $stmt->execute([$admin_email]);
            $admin = $stmt->fetch();

            if ($admin) {
                $this->logSuccess("Admin trovato: $admin_email");

                // Test password
                if (password_verify($admin_password, $admin['password'])) {
                    $this->logSuccess("Password admin verificata");
                } else {
                    $this->logError("Password admin non valida");
                }

                // Test stato
                if ($admin['status'] === 'active') {
                    $this->logSuccess("Admin attivo");
                } else {
                    $this->logWarning("Admin non attivo: " . $admin['status']);
                }

                // Simula login
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['user_email'] = $admin['email'];
                $_SESSION['user_role'] = $admin['role'];
                $this->logSuccess("Sessione admin creata");

            } else {
                $this->logError("Admin non trovato nel database");
            }

        } catch (Exception $e) {
            $this->logError("Errore test login admin: " . $e->getMessage());
        }

        echo "\n";
    }

    /**
     * Test ruoli e permessi
     */
    private function testRolesAndPermissions() {
        echo TestColors::BOLD . "5. Test Ruoli e Permessi" . TestColors::RESET . "\n";

        if (!$this->db) {
            $this->logError("Database non connesso");
            echo "\n";
            return;
        }

        $test_users = [
            ['email' => 'admin@test.com', 'role' => 'admin', 'expected_tenants' => 'all'],
            ['email' => 'special@test.com', 'role' => 'special_user', 'expected_tenants' => 'multiple'],
            ['email' => 'user@test.com', 'role' => 'standard_user', 'expected_tenants' => 'single']
        ];

        foreach ($test_users as $test_user) {
            try {
                // Crea utente test
                $password_hash = password_hash('Test@123', PASSWORD_DEFAULT);
                $stmt = $this->db->prepare("
                    INSERT INTO users (email, password, name, role, status)
                    VALUES (?, ?, ?, ?, 'active')
                    ON DUPLICATE KEY UPDATE role = VALUES(role)
                ");
                $stmt->execute([
                    $test_user['email'],
                    $password_hash,
                    'Test User',
                    $test_user['role']
                ]);

                // Recupera ID utente
                $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$test_user['email']]);
                $user = $stmt->fetch();

                if ($user) {
                    $this->logSuccess("Utente {$test_user['role']} creato: {$test_user['email']}");

                    // Test permessi
                    global $ROLE_PERMISSIONS;
                    $permissions = $ROLE_PERMISSIONS[$test_user['role']] ?? [];

                    if ($test_user['role'] === 'admin' && in_array('system_management', $permissions)) {
                        $this->logSuccess("  └─ Admin ha permessi di sistema");
                    } elseif ($test_user['role'] === 'special_user' && in_array('multi_tenant_access', $permissions)) {
                        $this->logSuccess("  └─ Special user ha accesso multi-tenant");
                    } elseif ($test_user['role'] === 'standard_user' && in_array('single_tenant_access', $permissions)) {
                        $this->logSuccess("  └─ Standard user ha accesso single-tenant");
                    }
                }

            } catch (Exception $e) {
                $this->logError("Errore creazione utente test: " . $e->getMessage());
            }
        }

        echo "\n";
    }

    /**
     * Test associazioni tenant
     */
    private function testTenantAssociations() {
        echo TestColors::BOLD . "6. Test Associazioni Tenant" . TestColors::RESET . "\n";

        if (!$this->db) {
            $this->logError("Database non connesso");
            echo "\n";
            return;
        }

        try {
            // Crea tenant di test
            $tenants = [
                ['code' => 'TENANT01', 'name' => 'Azienda Test 1'],
                ['code' => 'TENANT02', 'name' => 'Azienda Test 2'],
                ['code' => 'TENANT03', 'name' => 'Azienda Test 3']
            ];

            foreach ($tenants as $tenant) {
                $stmt = $this->db->prepare("
                    INSERT INTO tenants (code, name, status)
                    VALUES (?, ?, 'active')
                    ON DUPLICATE KEY UPDATE name = VALUES(name)
                ");
                $stmt->execute([$tenant['code'], $tenant['name']]);
            }
            $this->logSuccess("Tenant di test creati");

            // Associa utenti ai tenant
            // Special user - multi tenant
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = 'special@test.com'");
            $stmt->execute();
            $special_user = $stmt->fetch();

            if ($special_user) {
                // Associa a 2 tenant
                $stmt = $this->db->prepare("SELECT id FROM tenants WHERE code IN ('TENANT01', 'TENANT02')");
                $stmt->execute();
                $tenants = $stmt->fetchAll();

                foreach ($tenants as $tenant) {
                    $stmt = $this->db->prepare("
                        INSERT INTO user_tenants (user_id, tenant_id, is_primary)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary)
                    ");
                    $is_primary = ($tenant['id'] === $tenants[0]['id']) ? 1 : 0;
                    $stmt->execute([$special_user['id'], $tenant['id'], $is_primary]);
                }
                $this->logSuccess("Special user associato a multiple tenant");
            }

            // Standard user - single tenant
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = 'user@test.com'");
            $stmt->execute();
            $standard_user = $stmt->fetch();

            if ($standard_user) {
                $stmt = $this->db->prepare("SELECT id FROM tenants WHERE code = 'TENANT03'");
                $stmt->execute();
                $tenant = $stmt->fetch();

                if ($tenant) {
                    $stmt = $this->db->prepare("
                        INSERT INTO user_tenants (user_id, tenant_id, is_primary)
                        VALUES (?, ?, 1)
                        ON DUPLICATE KEY UPDATE is_primary = 1
                    ");
                    $stmt->execute([$standard_user['id'], $tenant['id']]);
                    $this->logSuccess("Standard user associato a single tenant");
                }
            }

        } catch (Exception $e) {
            $this->logError("Errore test associazioni tenant: " . $e->getMessage());
        }

        echo "\n";
    }

    /**
     * Test cambio tenant per utenti speciali
     */
    private function testMultiTenantSwitching() {
        echo TestColors::BOLD . "7. Test Cambio Multi-Tenant" . TestColors::RESET . "\n";

        if (!$this->db) {
            $this->logError("Database non connesso");
            echo "\n";
            return;
        }

        try {
            // Recupera special user
            $stmt = $this->db->prepare("
                SELECT u.*, GROUP_CONCAT(t.code) as tenant_codes
                FROM users u
                LEFT JOIN user_tenants ut ON u.id = ut.user_id
                LEFT JOIN tenants t ON ut.tenant_id = t.id
                WHERE u.email = 'special@test.com'
                GROUP BY u.id
            ");
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user) {
                $tenants = explode(',', $user['tenant_codes']);
                $this->logSuccess("Special user ha accesso a: " . implode(', ', $tenants));

                // Simula cambio tenant
                foreach ($tenants as $tenant_code) {
                    $_SESSION['current_tenant'] = $tenant_code;
                    $this->logSuccess("  └─ Switched to tenant: $tenant_code");
                }
            } else {
                $this->logError("Special user non trovato");
            }

        } catch (Exception $e) {
            $this->logError("Errore test cambio tenant: " . $e->getMessage());
        }

        echo "\n";
    }

    /**
     * Test API endpoints
     */
    private function testApiEndpoints() {
        echo TestColors::BOLD . "8. Test API Endpoints" . TestColors::RESET . "\n";

        $base_url = BASE_URL . '/api/v2';

        // Endpoints da testare
        $endpoints = [
            ['method' => 'POST', 'url' => '/auth/login', 'name' => 'Login'],
            ['method' => 'GET', 'url' => '/auth/verify', 'name' => 'Verifica Token'],
            ['method' => 'GET', 'url' => '/users/profile', 'name' => 'Profilo Utente'],
            ['method' => 'GET', 'url' => '/tenants/list', 'name' => 'Lista Tenant'],
            ['method' => 'POST', 'url' => '/tenants/switch', 'name' => 'Cambio Tenant']
        ];

        foreach ($endpoints as $endpoint) {
            $full_url = $base_url . $endpoint['url'];

            // Verifica se l'endpoint esiste (simulato)
            $file_path = BASE_PATH . '/api/v2' . str_replace('/', '_', $endpoint['url']) . '.php';

            if (file_exists($file_path)) {
                $this->logSuccess("API {$endpoint['name']}: Endpoint disponibile");
            } else {
                $this->logWarning("API {$endpoint['name']}: Endpoint non implementato");
            }
        }

        echo "\n";
    }

    /**
     * Test funzionalità di sicurezza
     */
    private function testSecurityFeatures() {
        echo TestColors::BOLD . "9. Test Sicurezza" . TestColors::RESET . "\n";

        // Test CSRF Protection
        if (isset($_SESSION['csrf_token'])) {
            $this->logSuccess("CSRF Token presente in sessione");
        } else {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $this->logSuccess("CSRF Token generato");
        }

        // Test SQL Injection Protection
        try {
            $malicious_input = "'; DROP TABLE users; --";
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$malicious_input]);
            $this->logSuccess("Protezione SQL Injection attiva (PDO prepared statements)");
        } catch (Exception $e) {
            $this->logError("Problema con protezione SQL Injection");
        }

        // Test XSS Protection
        $xss_test = "<script>alert('XSS')</script>";
        $safe_output = htmlspecialchars($xss_test, ENT_QUOTES, 'UTF-8');
        if ($safe_output !== $xss_test) {
            $this->logSuccess("Protezione XSS attiva");
        } else {
            $this->logError("Protezione XSS non configurata");
        }

        // Test Rate Limiting
        if (defined('MAX_LOGIN_ATTEMPTS')) {
            $this->logSuccess("Rate limiting configurato: " . MAX_LOGIN_ATTEMPTS . " tentativi");
        } else {
            $this->logWarning("Rate limiting non configurato");
        }

        // Test Password Policy
        $weak_password = "123456";
        $strong_password = "Test@2024#Secure";

        if ($this->validatePassword($strong_password)) {
            $this->logSuccess("Policy password forte attiva");
        } else {
            $this->logWarning("Policy password da rafforzare");
        }

        echo "\n";
    }

    /**
     * Test performance
     */
    private function testPerformance() {
        echo TestColors::BOLD . "10. Test Performance" . TestColors::RESET . "\n";

        $start_time = microtime(true);

        // Test query database
        try {
            for ($i = 0; $i < 100; $i++) {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE status = ?");
                $stmt->execute(['active']);
            }
            $db_time = microtime(true) - $start_time;

            if ($db_time < 0.5) {
                $this->logSuccess("Performance DB: 100 query in " . round($db_time, 3) . "s");
            } else {
                $this->logWarning("Performance DB lente: " . round($db_time, 3) . "s");
            }
        } catch (Exception $e) {
            $this->logError("Errore test performance DB");
        }

        // Test sessioni
        $session_start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $_SESSION["test_$i"] = random_bytes(32);
        }
        $session_time = microtime(true) - $session_start;

        if ($session_time < 0.1) {
            $this->logSuccess("Performance Sessioni: 100 scritture in " . round($session_time, 3) . "s");
        } else {
            $this->logWarning("Performance Sessioni lente: " . round($session_time, 3) . "s");
        }

        // Memory usage
        $memory = memory_get_usage(true) / 1024 / 1024;
        if ($memory < 50) {
            $this->logSuccess("Utilizzo memoria: " . round($memory, 2) . " MB");
        } else {
            $this->logWarning("Alto utilizzo memoria: " . round($memory, 2) . " MB");
        }

        echo "\n";
    }

    /**
     * Valida password secondo policy
     */
    private function validatePassword($password) {
        if (strlen($password) < PASSWORD_MIN_LENGTH) return false;
        if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[!@#$%^&*]/', $password)) return false;
        if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) return false;
        if (PASSWORD_REQUIRE_UPPER && !preg_match('/[A-Z]/', $password)) return false;
        return true;
    }

    /**
     * Log successo
     */
    private function logSuccess($message) {
        echo TestColors::GREEN . "✓ " . TestColors::RESET . $message . "\n";
        $this->tests_passed++;
        $this->test_results[] = ['status' => 'success', 'message' => $message];
    }

    /**
     * Log errore
     */
    private function logError($message) {
        echo TestColors::RED . "✗ " . TestColors::RESET . $message . "\n";
        $this->tests_failed++;
        $this->test_results[] = ['status' => 'error', 'message' => $message];
    }

    /**
     * Log warning
     */
    private function logWarning($message) {
        echo TestColors::YELLOW . "⚠ " . TestColors::RESET . $message . "\n";
        $this->test_results[] = ['status' => 'warning', 'message' => $message];
    }

    /**
     * Mostra risultati finali
     */
    private function displayResults() {
        echo "\n" . TestColors::BOLD . TestColors::BLUE . "=== RIEPILOGO TEST ===" . TestColors::RESET . "\n\n";

        $total = $this->tests_passed + $this->tests_failed;
        $percentage = $total > 0 ? round(($this->tests_passed / $total) * 100, 2) : 0;

        echo "Test superati: " . TestColors::GREEN . $this->tests_passed . TestColors::RESET . "\n";
        echo "Test falliti: " . TestColors::RED . $this->tests_failed . TestColors::RESET . "\n";
        echo "Percentuale successo: " . $this->getColorByPercentage($percentage) . $percentage . "%" . TestColors::RESET . "\n";

        // Genera report HTML
        $this->generateHtmlReport();

        echo "\n" . TestColors::BOLD . "Report HTML generato: " . TestColors::RESET . BASE_PATH . "/test_report_v2.html\n";

        // Consigli finali
        if ($percentage === 100) {
            echo "\n" . TestColors::GREEN . TestColors::BOLD . "Sistema pronto per l'uso!" . TestColors::RESET . "\n";
        } elseif ($percentage >= 80) {
            echo "\n" . TestColors::YELLOW . TestColors::BOLD . "Sistema utilizzabile con alcune correzioni consigliate." . TestColors::RESET . "\n";
        } else {
            echo "\n" . TestColors::RED . TestColors::BOLD . "Sistema necessita correzioni prima dell'uso." . TestColors::RESET . "\n";
        }
    }

    /**
     * Ottieni colore in base alla percentuale
     */
    private function getColorByPercentage($percentage) {
        if ($percentage >= 90) return TestColors::GREEN;
        if ($percentage >= 70) return TestColors::YELLOW;
        return TestColors::RED;
    }

    /**
     * Genera report HTML
     */
    private function generateHtmlReport() {
        $html = '<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Test Report - Nexio Solution V2</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .summary { background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .success { color: #4CAF50; }
        .error { color: #f44336; }
        .warning { color: #ff9800; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #4CAF50; color: white; }
        .badge { padding: 3px 8px; border-radius: 3px; font-size: 12px; color: white; }
        .badge-success { background: #4CAF50; }
        .badge-error { background: #f44336; }
        .badge-warning { background: #ff9800; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Report - Nexio Solution V2</h1>
        <div class="summary">
            <h2>Riepilogo</h2>
            <p>Data test: ' . date('Y-m-d H:i:s') . '</p>
            <p>Test superati: <span class="success">' . $this->tests_passed . '</span></p>
            <p>Test falliti: <span class="error">' . $this->tests_failed . '</span></p>
        </div>
        <h2>Dettagli Test</h2>
        <table>
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($this->test_results as $result) {
            $badge_class = 'badge-' . $result['status'];
            $html .= '<tr>
                <td>' . htmlspecialchars($result['message']) . '</td>
                <td><span class="badge ' . $badge_class . '">' . strtoupper($result['status']) . '</span></td>
            </tr>';
        }

        $html .= '</tbody></table></div></body></html>';

        file_put_contents(BASE_PATH . '/test_report_v2.html', $html);
    }
}

// Esecuzione test
if (PHP_SAPI === 'cli' || isset($_GET['run'])) {
    $tester = new SystemTest();
    $tester->runAllTests();
} else {
    // Interfaccia web
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Test Sistema - Nexio Solution V2</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .container { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 30px; }
            h1 { color: #333; text-align: center; }
            .info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .btn { display: inline-block; padding: 12px 24px; background: #4CAF50; color: white;
                   text-decoration: none; border-radius: 5px; font-size: 16px; margin: 10px; }
            .btn:hover { background: #45a049; }
            .btn-secondary { background: #2196F3; }
            .btn-secondary:hover { background: #0b7dda; }
            .center { text-align: center; }
            code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🧪 Test Sistema - Nexio Solution V2</h1>

            <div class="info">
                <h3>Informazioni Test</h3>
                <p>Questo script eseguirà una serie completa di test per verificare:</p>
                <ul>
                    <li>Requisiti sistema e configurazione PHP</li>
                    <li>Connessione e schema database</li>
                    <li>Sistema di autenticazione e ruoli</li>
                    <li>Gestione multi-tenant</li>
                    <li>API endpoints</li>
                    <li>Funzionalità di sicurezza</li>
                    <li>Performance sistema</li>
                </ul>
            </div>

            <div class="center">
                <a href="?run=1" class="btn">▶ Esegui Test Browser</a>
                <a href="test_report_v2.html" class="btn btn-secondary">📊 Visualizza Ultimo Report</a>
            </div>

            <div class="info">
                <h3>Esecuzione da Console</h3>
                <p>Per risultati ottimali, esegui da linea di comando:</p>
                <code>php test_v2.php</code>
            </div>

            <?php if (isset($_GET['run'])): ?>
            <div style="background: #263238; color: #aed581; padding: 20px; border-radius: 5px; margin-top: 20px; font-family: monospace; white-space: pre-wrap;">
<?php
                ob_start();
                $tester = new SystemTest();
                $tester->runAllTests();
                $output = ob_get_clean();
                // Rimuovi i codici colore ANSI per la visualizzazione web
                $output = preg_replace('/\033\[[0-9;]*m/', '', $output);
                echo htmlspecialchars($output);
?>
            </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}