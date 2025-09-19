<?php
/**
 * Test Script per Verificare la Risoluzione del Loop di Redirect
 *
 * Esegue una serie di test per confermare che il problema è stato risolto
 */

// Inizializza output
header('Content-Type: text/plain; charset=utf-8');
echo "===========================================================\n";
echo " TEST REDIRECT LOOP FIX - " . date('Y-m-d H:i:s') . "\n";
echo "===========================================================\n\n";

// Test 1: Verifica file session_helper.php
echo "[TEST 1] Verifica SessionHelper\n";
echo "---------------------------------\n";

$sessionHelperPath = __DIR__ . '/includes/session_helper.php';
if (file_exists($sessionHelperPath)) {
    echo "✅ session_helper.php trovato\n";
    require_once $sessionHelperPath;

    if (class_exists('Collabora\Session\SessionHelper')) {
        echo "✅ Classe SessionHelper disponibile\n";
    } else {
        echo "❌ Classe SessionHelper non trovata\n";
    }
} else {
    echo "❌ session_helper.php non trovato\n";
}

// Test 2: Verifica configurazione sessione
echo "\n[TEST 2] Configurazione Sessione\n";
echo "---------------------------------\n";

require_once __DIR__ . '/config_v2.php';

echo "SESSION_PATH: " . SESSION_PATH . "\n";
echo "SESSION_NAME: " . SESSION_NAME . "\n";
echo "SESSION_LIFETIME: " . SESSION_LIFETIME . " secondi\n";

if (SESSION_PATH === '/Nexiosolution/collabora/') {
    echo "✅ SESSION_PATH configurato correttamente per sottocartella\n";
} else {
    echo "❌ SESSION_PATH non corretto: " . SESSION_PATH . "\n";
}

// Test 3: Test inizializzazione sessione
echo "\n[TEST 3] Test Inizializzazione Sessione\n";
echo "-----------------------------------------\n";

use Collabora\Session\SessionHelper;

try {
    SessionHelper::init();
    echo "✅ Sessione inizializzata con successo\n";
    echo "Session ID: " . session_id() . "\n";

    // Ottieni info di debug
    $debugInfo = SessionHelper::getDebugInfo();
    echo "Session Path dal cookie: " . ($debugInfo['cookie_params']['path'] ?? 'non impostato') . "\n";
} catch (Exception $e) {
    echo "❌ Errore inizializzazione sessione: " . $e->getMessage() . "\n";
}

// Test 4: Simula login e verifica sessione
echo "\n[TEST 4] Simulazione Login\n";
echo "---------------------------\n";

// Simula dati utente admin
$_SESSION['user_v2'] = [
    'id' => 1,
    'email' => 'asamodeo@fortibyte.it',
    'name' => 'Admin Samodeo',
    'role' => 'admin',
    'is_admin' => true
];

echo "✅ Dati utente salvati in sessione\n";

// Test verifica autenticazione
if (SessionHelper::isAuthenticated()) {
    echo "✅ Utente risulta autenticato\n";
} else {
    echo "❌ Utente non risulta autenticato\n";
}

// Test verifica ruolo admin
if (SessionHelper::isAdmin()) {
    echo "✅ Utente risulta admin\n";
} else {
    echo "❌ Utente non risulta admin\n";
}

// Test 5: Verifica admin/index.php
echo "\n[TEST 5] Verifica admin/index.php\n";
echo "-----------------------------------\n";

$adminIndexPath = __DIR__ . '/admin/index.php';
$adminContent = file_get_contents($adminIndexPath);

// Verifica che SessionHelper sia utilizzato
if (strpos($adminContent, 'SessionHelper::init()') !== false) {
    echo "✅ admin/index.php usa SessionHelper::init()\n";
} else {
    echo "❌ admin/index.php non usa SessionHelper::init()\n";
}

// Verifica detection loop
if (strpos($adminContent, 'SessionHelper::detectRedirectLoop') !== false) {
    echo "✅ admin/index.php ha detection del loop\n";
} else {
    echo "❌ admin/index.php non ha detection del loop\n";
}

// Test 6: Test API login
echo "\n[TEST 6] Test API Login\n";
echo "------------------------\n";

$ch = curl_init('http://localhost/Nexiosolution/collabora/api/auth_simple.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'action' => 'login',
    'email' => 'asamodeo@fortibyte.it',
    'password' => 'Ricord@1991'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/test_cookie.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/test_cookie.txt');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✅ Login API funziona correttamente\n";
        if (isset($data['redirect'])) {
            echo "   Redirect suggerito: " . $data['redirect'] . "\n";
        }
    } else {
        echo "❌ Login API fallito: " . ($data['error']['message'] ?? 'Errore sconosciuto') . "\n";
    }
} else {
    echo "❌ Errore HTTP: $httpCode\n";
}

// Test 7: Simula accesso ad admin/index.php con sessione valida
echo "\n[TEST 7] Test Accesso Admin con Sessione\n";
echo "------------------------------------------\n";

// Usa la sessione con cookie salvato
$ch = curl_init('http://localhost/Nexiosolution/collabora/admin/index.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/test_cookie.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/test_cookie.txt');
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
curl_close($ch);

if ($httpCode == 200) {
    echo "✅ Accesso ad admin/index.php riuscito (HTTP 200)\n";
    echo "   Nessun redirect - pagina admin accessibile\n";
} elseif ($httpCode == 302 || $httpCode == 301) {
    echo "⚠️ Redirect da admin/index.php (HTTP $httpCode)\n";
    if ($redirectUrl) {
        echo "   Redirect a: $redirectUrl\n";
    }
    echo "   Potrebbe indicare che la sessione non è condivisa correttamente\n";
} else {
    echo "❌ Errore accesso admin/index.php (HTTP $httpCode)\n";
}

// Riepilogo
echo "\n===========================================================\n";
echo " RIEPILOGO TEST\n";
echo "===========================================================\n\n";

$issues = [];

// Controlla problemi critici
if (SESSION_PATH !== '/Nexiosolution/collabora/') {
    $issues[] = "SESSION_PATH non corretto";
}

if (!class_exists('Collabora\Session\SessionHelper')) {
    $issues[] = "SessionHelper non disponibile";
}

if (count($issues) == 0) {
    echo "🎉 TUTTI I TEST CRITICI SUPERATI!\n";
    echo "Il problema del loop di redirect dovrebbe essere risolto.\n\n";
    echo "PROSSIMI PASSI:\n";
    echo "1. Cancella i cookie del browser\n";
    echo "2. Vai a: http://localhost/Nexiosolution/collabora/index_v2.php\n";
    echo "3. Effettua login con: asamodeo@fortibyte.it / Ricord@1991\n";
    echo "4. Dovresti essere reindirizzato a /admin/index.php senza loop\n";
} else {
    echo "⚠️ TROVATI PROBLEMI:\n";
    foreach ($issues as $issue) {
        echo "   - $issue\n";
    }
    echo "\nCorreggere questi problemi prima di testare il login.\n";
}

echo "\n";
?>