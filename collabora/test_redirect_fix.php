<?php declare(strict_types=1);

/**
 * Test Script per verificare il fix del loop di reindirizzamento
 *
 * Questo script verifica che:
 * 1. Le sessioni siano configurate correttamente
 * 2. Il path dei cookie sia corretto per la sottocartella
 * 3. Non ci siano loop di reindirizzamento
 * 4. L'autenticazione funzioni correttamente
 */

// Configurazione test
$testConfig = [
    'admin_email' => 'asamodeo@fortibyte.it',
    'admin_password' => 'Ricord@1991',
    'base_url' => 'http://localhost/Nexiosolution/collabora'
];

// Colori per output console
$colors = [
    'success' => "\033[0;32m",
    'error' => "\033[0;31m",
    'warning' => "\033[0;33m",
    'info' => "\033[0;36m",
    'reset' => "\033[0m"
];

function printTest($message, $status = 'info') {
    global $colors;
    $symbol = match($status) {
        'success' => '✓',
        'error' => '✗',
        'warning' => '!',
        'info' => 'ℹ',
        default => '-'
    };
    echo $colors[$status] . "[$symbol] " . $message . $colors['reset'] . PHP_EOL;
}

function testResult($test, $passed) {
    if ($passed) {
        printTest("$test: PASSED", 'success');
        return true;
    } else {
        printTest("$test: FAILED", 'error');
        return false;
    }
}

// Header
echo "\n";
echo "========================================\n";
echo "  TEST FIX REDIRECT LOOP - NEXIO COLLABORA\n";
echo "========================================\n\n";

// Test 1: Verifica configurazione PHP
printTest("Test 1: Verifica Configurazione PHP", 'info');
$phpTests = [
    'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'Session module loaded' => extension_loaded('session'),
    'PDO MySQL loaded' => extension_loaded('pdo_mysql'),
];

foreach ($phpTests as $test => $result) {
    testResult($test, $result);
}

// Test 2: Verifica file di configurazione
echo "\n";
printTest("Test 2: Verifica File di Configurazione", 'info');

require_once __DIR__ . '/config_v2.php';

$configTests = [
    'SESSION_PATH defined' => defined('SESSION_PATH'),
    'SESSION_PATH correct' => defined('SESSION_PATH') && SESSION_PATH === '/Nexiosolution/collabora/',
    'SESSION_NAME defined' => defined('SESSION_NAME'),
    'DB configuration defined' => defined('DB_HOST') && defined('DB_NAME'),
];

foreach ($configTests as $test => $result) {
    testResult($test, $result);
}

if (defined('SESSION_PATH')) {
    printTest("SESSION_PATH value: " . SESSION_PATH, 'info');
}

// Test 3: Verifica SessionHelper
echo "\n";
printTest("Test 3: Verifica SessionHelper", 'info');

require_once __DIR__ . '/includes/session_helper.php';
use Collabora\Session\SessionHelper;

try {
    $sessionInit = SessionHelper::init();
    testResult("SessionHelper initialization", $sessionInit);

    $debugInfo = SessionHelper::getDebugInfo();
    testResult("Get debug info", !empty($debugInfo));

    printTest("Session ID: " . ($debugInfo['session_id'] ?? 'not set'), 'info');
    printTest("Session Name: " . ($debugInfo['session_name'] ?? 'not set'), 'info');

    $cookieParams = $debugInfo['session_params'] ?? [];
    printTest("Cookie Path: " . ($cookieParams['path'] ?? 'not set'), 'info');

    testResult("Cookie path matches config",
        isset($cookieParams['path']) && $cookieParams['path'] === SESSION_PATH);

} catch (Exception $e) {
    testResult("SessionHelper", false);
    printTest("Error: " . $e->getMessage(), 'error');
}

// Test 4: Verifica connessione database
echo "\n";
printTest("Test 4: Verifica Database", 'info');

require_once __DIR__ . '/includes/db.php';

try {
    $pdo = getDbConnection();
    testResult("Database connection", true);

    // Verifica tabelle necessarie
    $tables = ['users', 'tenants', 'user_tenant_associations'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        testResult("Table '$table' exists", $exists);
    }

    // Verifica utente admin
    $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE email = ?");
    $stmt->execute([$testConfig['admin_email']]);
    $adminUser = $stmt->fetch();

    testResult("Admin user exists", !empty($adminUser));
    if ($adminUser) {
        printTest("Admin user: " . $adminUser['email'] . " (role: " . $adminUser['role'] . ")", 'info');
    }

} catch (Exception $e) {
    testResult("Database connection", false);
    printTest("Error: " . $e->getMessage(), 'error');
}

// Test 5: Verifica SimpleAuth
echo "\n";
printTest("Test 5: Verifica SimpleAuth Login", 'info');

require_once __DIR__ . '/includes/SimpleAuth.php';

try {
    $auth = new SimpleAuth();

    // Test login
    $loginResult = $auth->login($testConfig['admin_email'], $testConfig['admin_password']);

    testResult("Login successful", $loginResult['success'] ?? false);

    if ($loginResult['success'] ?? false) {
        printTest("User ID: " . $loginResult['user']['id'], 'info');
        printTest("User Role: " . $loginResult['user']['role'], 'info');
        printTest("Is Admin: " . ($loginResult['user']['is_admin'] ? 'Yes' : 'No'), 'info');

        // Verifica sessione dopo login
        testResult("User authenticated", SessionHelper::isAuthenticated());
        testResult("User is admin", SessionHelper::isAdmin());
    }

    // Cleanup - logout
    $auth->logout();

} catch (Exception $e) {
    testResult("SimpleAuth login", false);
    printTest("Error: " . $e->getMessage(), 'error');
}

// Test 6: Test redirect loop detection
echo "\n";
printTest("Test 6: Verifica Prevenzione Loop", 'info');

// Simula redirect multipli
for ($i = 1; $i <= 4; $i++) {
    $hasLoop = SessionHelper::detectRedirectLoop('test_context', 3);

    if ($i <= 3) {
        testResult("Redirect $i - No loop detected", !$hasLoop);
    } else {
        testResult("Redirect $i - Loop detected", $hasLoop);
    }
}

// Reset counter
SessionHelper::resetRedirectCount('test_context');
testResult("Reset redirect counter", !SessionHelper::detectRedirectLoop('test_context', 3));

// Test 7: Test file permissions
echo "\n";
printTest("Test 7: Verifica Permessi File", 'info');

$filesToCheck = [
    '/admin/index.php' => 'Admin index',
    '/index_v2.php' => 'Main index',
    '/includes/SimpleAuth.php' => 'SimpleAuth',
    '/includes/session_helper.php' => 'SessionHelper',
    '/config_v2.php' => 'Configuration',
];

foreach ($filesToCheck as $file => $name) {
    $fullPath = __DIR__ . $file;
    $exists = file_exists($fullPath);
    $readable = $exists && is_readable($fullPath);

    testResult("$name exists and readable", $exists && $readable);
}

// Test 8: Simula navigazione admin
echo "\n";
printTest("Test 8: Simula Accesso Admin", 'info');

// Effettua login
try {
    $auth = new SimpleAuth();
    $loginResult = $auth->login($testConfig['admin_email'], $testConfig['admin_password']);

    if ($loginResult['success'] ?? false) {
        testResult("Login completed", true);

        // Verifica che l'utente possa accedere all'area admin
        $canAccessAdmin = SessionHelper::isAdmin();
        testResult("Can access admin area", $canAccessAdmin);

        // Verifica che non ci sia loop
        $hasLoop = false;
        for ($i = 0; $i < 3; $i++) {
            if (SessionHelper::detectRedirectLoop('admin_access')) {
                $hasLoop = true;
                break;
            }
        }

        testResult("No redirect loop on admin access", !$hasLoop);

        // Cleanup
        SessionHelper::resetRedirectCount('admin_access');
        $auth->logout();
    }
} catch (Exception $e) {
    testResult("Admin access simulation", false);
    printTest("Error: " . $e->getMessage(), 'error');
}

// Riepilogo finale
echo "\n";
echo "========================================\n";
echo "  RIEPILOGO TEST\n";
echo "========================================\n\n";

$recommendations = [];

if (!defined('SESSION_PATH') || SESSION_PATH !== '/Nexiosolution/collabora/') {
    $recommendations[] = "Aggiorna SESSION_PATH in config_v2.php a '/Nexiosolution/collabora/'";
}

if (empty($adminUser)) {
    $recommendations[] = "Crea l'utente admin nel database con email: " . $testConfig['admin_email'];
}

if (count($recommendations) > 0) {
    printTest("Raccomandazioni:", 'warning');
    foreach ($recommendations as $rec) {
        printTest("  - $rec", 'warning');
    }
} else {
    printTest("Tutti i test sono passati! Il sistema dovrebbe funzionare correttamente.", 'success');
}

echo "\n";
printTest("Per testare nel browser:", 'info');
printTest("  1. Apri: " . $testConfig['base_url'] . "/index_v2.php", 'info');
printTest("  2. Effettua login con:", 'info');
printTest("     Email: " . $testConfig['admin_email'], 'info');
printTest("     Password: " . $testConfig['admin_password'], 'info');
printTest("  3. Verifica di essere reindirizzato a /admin/index.php senza loop", 'info');

echo "\n";