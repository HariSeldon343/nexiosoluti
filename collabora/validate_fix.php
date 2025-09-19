<?php declare(strict_types=1);

/**
 * Script di validazione del fix redirect loop
 * Eseguibile dal browser per verificare la configurazione
 */

// Prevent output before headers
ob_start();

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validazione Fix Redirect Loop</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #2563EB;
            padding-bottom: 10px;
        }
        .test-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .test-item:last-child {
            border-bottom: none;
        }
        .status {
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 4px;
        }
        .status.pass {
            background: #10B981;
            color: white;
        }
        .status.fail {
            background: #EF4444;
            color: white;
        }
        .status.warning {
            background: #F59E0B;
            color: white;
        }
        .status.info {
            background: #3B82F6;
            color: white;
        }
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .recommendation {
            background: #FEF3C7;
            border-left: 4px solid #F59E0B;
            padding: 15px;
            margin-top: 20px;
        }
        .success-message {
            background: #D1FAE5;
            border-left: 4px solid #10B981;
            padding: 15px;
            margin-top: 20px;
        }
        .error-message {
            background: #FEE2E2;
            border-left: 4px solid #EF4444;
            padding: 15px;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f3f4f6;
            padding: 10px;
            text-align: left;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <h1>🔧 Validazione Fix Redirect Loop - Nexio Collabora</h1>

    <?php
    $allTestsPassed = true;
    $recommendations = [];

    // Test 1: Configurazione File
    ?>
    <div class="test-section">
        <h2>1️⃣ Verifica Configurazione</h2>
        <?php
        require_once __DIR__ . '/config_v2.php';

        $configTests = [
            'SESSION_PATH definito' => defined('SESSION_PATH'),
            'SESSION_PATH corretto (/Nexiosolution/collabora/)' => defined('SESSION_PATH') && SESSION_PATH === '/Nexiosolution/collabora/',
            'SESSION_NAME definito' => defined('SESSION_NAME'),
            'SESSION_LIFETIME definito' => defined('SESSION_LIFETIME'),
            'Database configurato' => defined('DB_HOST') && defined('DB_NAME'),
        ];

        foreach ($configTests as $test => $result) {
            echo '<div class="test-item">';
            echo '<span>' . $test . '</span>';
            echo '<span class="status ' . ($result ? 'pass' : 'fail') . '">' . ($result ? 'PASS' : 'FAIL') . '</span>';
            echo '</div>';
            if (!$result) $allTestsPassed = false;
        }

        if (defined('SESSION_PATH')) {
            echo '<div class="test-item">';
            echo '<span>Valore SESSION_PATH</span>';
            echo '<span class="status info">' . SESSION_PATH . '</span>';
            echo '</div>';
        }
        ?>
    </div>

    <?php
    // Test 2: File System
    ?>
    <div class="test-section">
        <h2>2️⃣ Verifica File Sistema</h2>
        <?php
        $files = [
            '/admin/index.php' => 'Admin Dashboard',
            '/includes/session_helper.php' => 'Session Helper',
            '/includes/SimpleAuth.php' => 'Simple Auth',
            '/config_v2.php' => 'Configurazione',
            '/index_v2.php' => 'Index principale',
        ];

        foreach ($files as $file => $name) {
            $fullPath = __DIR__ . $file;
            $exists = file_exists($fullPath);
            echo '<div class="test-item">';
            echo '<span>' . $name . ' (' . $file . ')</span>';
            echo '<span class="status ' . ($exists ? 'pass' : 'fail') . '">' . ($exists ? 'EXISTS' : 'MISSING') . '</span>';
            echo '</div>';
            if (!$exists) {
                $allTestsPassed = false;
                $recommendations[] = "File mancante: $file";
            }
        }
        ?>
    </div>

    <?php
    // Test 3: Database
    ?>
    <div class="test-section">
        <h2>3️⃣ Verifica Database</h2>
        <?php
        try {
            require_once __DIR__ . '/includes/db.php';
            $pdo = getDbConnection();

            echo '<div class="test-item">';
            echo '<span>Connessione Database</span>';
            echo '<span class="status pass">CONNECTED</span>';
            echo '</div>';

            // Check tables
            $requiredTables = ['users', 'tenants'];
            foreach ($requiredTables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                $exists = $stmt->rowCount() > 0;
                echo '<div class="test-item">';
                echo '<span>Tabella: ' . $table . '</span>';
                echo '<span class="status ' . ($exists ? 'pass' : 'fail') . '">' . ($exists ? 'EXISTS' : 'MISSING') . '</span>';
                echo '</div>';
                if (!$exists) {
                    $allTestsPassed = false;
                    $recommendations[] = "Tabella mancante: $table";
                }
            }

            // Check admin user
            $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE email = 'asamodeo@fortibyte.it'");
            $stmt->execute();
            $admin = $stmt->fetch();

            echo '<div class="test-item">';
            echo '<span>Admin User (asamodeo@fortibyte.it)</span>';
            echo '<span class="status ' . ($admin ? 'pass' : 'fail') . '">' . ($admin ? 'EXISTS' : 'MISSING') . '</span>';
            echo '</div>';

            if (!$admin) {
                $allTestsPassed = false;
                $recommendations[] = "Utente admin non trovato nel database";
            }

        } catch (Exception $e) {
            echo '<div class="test-item">';
            echo '<span>Connessione Database</span>';
            echo '<span class="status fail">ERROR: ' . htmlspecialchars($e->getMessage()) . '</span>';
            echo '</div>';
            $allTestsPassed = false;
            $recommendations[] = "Errore database: " . $e->getMessage();
        }
        ?>
    </div>

    <?php
    // Test 4: Session
    ?>
    <div class="test-section">
        <h2>4️⃣ Verifica Sessione</h2>
        <?php
        try {
            require_once __DIR__ . '/includes/session_helper.php';
            use Collabora\Session\SessionHelper;

            $sessionInit = SessionHelper::init();
            echo '<div class="test-item">';
            echo '<span>Inizializzazione SessionHelper</span>';
            echo '<span class="status ' . ($sessionInit ? 'pass' : 'fail') . '">' . ($sessionInit ? 'OK' : 'FAILED') . '</span>';
            echo '</div>';

            $debugInfo = SessionHelper::getDebugInfo();

            echo '<div class="test-item">';
            echo '<span>Session ID</span>';
            echo '<span class="status info">' . substr($debugInfo['session_id'], 0, 16) . '...</span>';
            echo '</div>';

            echo '<div class="test-item">';
            echo '<span>Session Name</span>';
            echo '<span class="status info">' . $debugInfo['session_name'] . '</span>';
            echo '</div>';

            $cookieParams = $debugInfo['session_params'];
            echo '<div class="test-item">';
            echo '<span>Cookie Path</span>';
            echo '<span class="status ' . ($cookieParams['path'] === SESSION_PATH ? 'pass' : 'warning') . '">' . $cookieParams['path'] . '</span>';
            echo '</div>';

            if ($cookieParams['path'] !== SESSION_PATH) {
                $recommendations[] = "Cookie path non corrisponde alla configurazione";
            }

        } catch (Exception $e) {
            echo '<div class="test-item">';
            echo '<span>SessionHelper</span>';
            echo '<span class="status fail">ERROR: ' . htmlspecialchars($e->getMessage()) . '</span>';
            echo '</div>';
            $allTestsPassed = false;
        }
        ?>
    </div>

    <?php
    // Test 5: Modifiche Chiave
    ?>
    <div class="test-section">
        <h2>5️⃣ Verifica Modifiche Implementate</h2>
        <?php
        // Check admin/index.php for session_start
        $adminContent = file_get_contents(__DIR__ . '/admin/index.php');
        $hasSessionHelper = strpos($adminContent, 'SessionHelper') !== false;
        $hasLoopPrevention = strpos($adminContent, 'detectRedirectLoop') !== false;

        echo '<div class="test-item">';
        echo '<span>admin/index.php usa SessionHelper</span>';
        echo '<span class="status ' . ($hasSessionHelper ? 'pass' : 'fail') . '">' . ($hasSessionHelper ? 'YES' : 'NO') . '</span>';
        echo '</div>';

        echo '<div class="test-item">';
        echo '<span>admin/index.php ha prevenzione loop</span>';
        echo '<span class="status ' . ($hasLoopPrevention ? 'pass' : 'fail') . '">' . ($hasLoopPrevention ? 'YES' : 'NO') . '</span>';
        echo '</div>';

        // Check SimpleAuth
        $authContent = file_get_contents(__DIR__ . '/includes/SimpleAuth.php');
        $hasSessionConfig = strpos($authContent, 'session_set_cookie_params') !== false;

        echo '<div class="test-item">';
        echo '<span>SimpleAuth.php configura sessione</span>';
        echo '<span class="status ' . ($hasSessionConfig ? 'pass' : 'fail') . '">' . ($hasSessionConfig ? 'YES' : 'NO') . '</span>';
        echo '</div>';
        ?>
    </div>

    <?php if ($allTestsPassed && empty($recommendations)): ?>
        <div class="success-message">
            <h3>✅ Tutti i test sono passati!</h3>
            <p>Il sistema è configurato correttamente. Il redirect loop dovrebbe essere risolto.</p>
            <p><strong>Prossimi passi:</strong></p>
            <ol>
                <li>Svuota la cache del browser e i cookie</li>
                <li>Vai a <a href="index_v2.php">index_v2.php</a></li>
                <li>Effettua login con le credenziali admin</li>
                <li>Verifica che non ci siano loop di reindirizzamento</li>
            </ol>
        </div>
    <?php else: ?>
        <div class="error-message">
            <h3>⚠️ Alcuni test non sono passati</h3>
            <p>Ci sono problemi da risolvere prima che il sistema funzioni correttamente.</p>
        </div>

        <?php if (!empty($recommendations)): ?>
            <div class="recommendation">
                <h3>📋 Raccomandazioni:</h3>
                <ul>
                    <?php foreach ($recommendations as $rec): ?>
                        <li><?php echo htmlspecialchars($rec); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="test-section">
        <h2>📊 Informazioni Sistema</h2>
        <table>
            <tr>
                <th>Parametro</th>
                <th>Valore</th>
            </tr>
            <tr>
                <td>PHP Version</td>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <td>Server Software</td>
                <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></td>
            </tr>
            <tr>
                <td>Document Root</td>
                <td><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'; ?></td>
            </tr>
            <tr>
                <td>Script Path</td>
                <td><?php echo __FILE__; ?></td>
            </tr>
            <tr>
                <td>Request URI</td>
                <td><?php echo $_SERVER['REQUEST_URI'] ?? 'N/A'; ?></td>
            </tr>
        </table>
    </div>

    <div class="test-section">
        <h2>🔍 Test Login Rapido</h2>
        <p>Usa questo form per testare rapidamente il login:</p>
        <form action="api/auth_simple.php" method="POST" style="max-width: 400px;">
            <div style="margin-bottom: 15px;">
                <label for="email" style="display: block; margin-bottom: 5px;">Email:</label>
                <input type="email" id="email" name="email" value="asamodeo@fortibyte.it" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label for="password" style="display: block; margin-bottom: 5px;">Password:</label>
                <input type="password" id="password" name="password" value="Ricord@1991" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <input type="hidden" name="action" value="login">
            <button type="submit" style="background: #2563EB; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Test Login</button>
        </form>
    </div>

    <?php
    // Clean any output buffer
    $output = ob_get_clean();
    echo $output;
    ?>
</body>
</html>