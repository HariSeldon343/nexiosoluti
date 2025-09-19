<?php
/**
 * Pagina di Validazione Sessione - Da aprire nel browser
 * Verifica che la sessione funzioni correttamente dopo il login
 */

// Inizializza sessione
require_once 'config_v2.php';
require_once 'includes/session_helper.php';

use Collabora\Session\SessionHelper;

// Inizializza la sessione
SessionHelper::init();

// Se richiesta azione di test login
if (isset($_GET['action']) && $_GET['action'] === 'test_login') {
    // Simula login admin
    $_SESSION['user_v2'] = [
        'id' => 1,
        'email' => 'asamodeo@fortibyte.it',
        'name' => 'Admin Samodeo',
        'role' => 'admin',
        'is_admin' => true
    ];
    $_SESSION['logged_in'] = true;
    $_SESSION['current_tenant_id'] = 1;

    header('Location: validate_session.php?login=success');
    exit;
}

// Se richiesta azione di logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: validate_session.php?logout=success');
    exit;
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validazione Sessione - Nexio Collabora</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: #111827;
            color: white;
            padding: 30px;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .header p {
            color: #9ca3af;
            font-size: 14px;
        }
        .content {
            padding: 30px;
        }
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .test-section h2 {
            font-size: 18px;
            color: #374151;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .test-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .test-item:last-child {
            border-bottom: none;
        }
        .test-label {
            flex: 1;
            font-size: 14px;
            color: #6b7280;
        }
        .test-value {
            font-family: monospace;
            font-size: 13px;
            padding: 4px 8px;
            background: white;
            border-radius: 4px;
            color: #374151;
        }
        .status {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status.ok {
            background: #d1fae5;
            color: #065f46;
        }
        .status.error {
            background: #fee2e2;
            color: #991b1b;
        }
        .status.warning {
            background: #fed7aa;
            color: #92400e;
        }
        .status svg {
            width: 16px;
            height: 16px;
            margin-right: 6px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #2563eb;
        }
        .btn.secondary {
            background: #6b7280;
        }
        .btn.secondary:hover {
            background: #4b5563;
        }
        .btn.success {
            background: #10b981;
        }
        .btn.success:hover {
            background: #059669;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .code-block {
            background: #1f2937;
            color: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Validazione Sessione</h1>
            <p>Verifica dello stato della sessione e del sistema di autenticazione</p>
        </div>

        <div class="content">
            <?php if (isset($_GET['login'])): ?>
            <div class="alert success">
                ✅ Login simulato con successo! La sessione è stata creata.
            </div>
            <?php elseif (isset($_GET['logout'])): ?>
            <div class="alert info">
                ℹ️ Logout effettuato. La sessione è stata terminata.
            </div>
            <?php endif; ?>

            <!-- Stato Sessione -->
            <div class="test-section">
                <h2>
                    <?php if (SessionHelper::isAuthenticated()): ?>
                    <span class="status ok">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Sessione Attiva
                    </span>
                    <?php else: ?>
                    <span class="status warning">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        Nessuna Sessione Attiva
                    </span>
                    <?php endif; ?>
                </h2>

                <div class="test-item">
                    <span class="test-label">Session ID:</span>
                    <span class="test-value"><?php echo session_id() ?: 'Non inizializzata'; ?></span>
                </div>

                <div class="test-item">
                    <span class="test-label">Session Name:</span>
                    <span class="test-value"><?php echo session_name(); ?></span>
                </div>

                <div class="test-item">
                    <span class="test-label">Cookie Path:</span>
                    <span class="test-value"><?php
                        $params = session_get_cookie_params();
                        echo $params['path'];
                    ?></span>
                </div>

                <div class="test-item">
                    <span class="test-label">Session Status:</span>
                    <span class="test-value"><?php
                        $status = session_status();
                        if ($status == PHP_SESSION_DISABLED) echo 'DISABLED';
                        elseif ($status == PHP_SESSION_NONE) echo 'NONE';
                        elseif ($status == PHP_SESSION_ACTIVE) echo 'ACTIVE';
                    ?></span>
                </div>
            </div>

            <!-- Dati Utente -->
            <?php if (SessionHelper::isAuthenticated()): ?>
            <div class="test-section">
                <h2>👤 Dati Utente</h2>
                <?php $user = SessionHelper::getCurrentUser(); ?>

                <div class="test-item">
                    <span class="test-label">ID:</span>
                    <span class="test-value"><?php echo $user['id'] ?? 'N/A'; ?></span>
                </div>

                <div class="test-item">
                    <span class="test-label">Email:</span>
                    <span class="test-value"><?php echo $user['email'] ?? 'N/A'; ?></span>
                </div>

                <div class="test-item">
                    <span class="test-label">Nome:</span>
                    <span class="test-value"><?php echo $user['name'] ?? 'N/A'; ?></span>
                </div>

                <div class="test-item">
                    <span class="test-label">Ruolo:</span>
                    <span class="test-value"><?php echo $user['role'] ?? 'N/A'; ?></span>
                </div>

                <div class="test-item">
                    <span class="test-label">È Admin:</span>
                    <span class="test-value"><?php echo SessionHelper::isAdmin() ? 'SI' : 'NO'; ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Test Accesso Admin -->
            <div class="test-section">
                <h2>🔐 Test Accesso Admin</h2>

                <?php if (SessionHelper::isAuthenticated()): ?>
                    <?php if (SessionHelper::isAdmin()): ?>
                    <div class="alert success">
                        ✅ Hai i permessi di amministratore. Puoi accedere a /admin/index.php
                    </div>
                    <?php else: ?>
                    <div class="alert error">
                        ❌ Non hai i permessi di amministratore. L'accesso a /admin/index.php verrà negato.
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                <div class="alert info">
                    ℹ️ Effettua prima il login per testare l'accesso admin.
                </div>
                <?php endif; ?>

                <div class="test-item">
                    <span class="test-label">Redirect Count (admin):</span>
                    <span class="test-value"><?php echo $_SESSION['redirect_count']['admin'] ?? 0; ?></span>
                </div>

                <div class="test-item">
                    <span class="test-label">Loop Detection Active:</span>
                    <span class="test-value"><?php echo class_exists('Collabora\Session\SessionHelper') ? 'SI' : 'NO'; ?></span>
                </div>
            </div>

            <!-- Debug Info -->
            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            <div class="test-section">
                <h2>🐛 Debug Info</h2>
                <div class="code-block">
                    <?php
                    $debug = SessionHelper::getDebugInfo();
                    echo htmlspecialchars(json_encode($debug, JSON_PRETTY_PRINT));
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Azioni -->
            <div class="test-section">
                <h2>⚡ Azioni Rapide</h2>

                <div class="actions">
                    <?php if (!SessionHelper::isAuthenticated()): ?>
                    <a href="?action=test_login" class="btn success">Simula Login Admin</a>
                    <a href="index_v2.php" class="btn">Vai al Login Reale</a>
                    <?php else: ?>
                    <a href="admin/index.php" class="btn">Prova Accesso Admin</a>
                    <a href="home_v2.php" class="btn secondary">Vai alla Home</a>
                    <a href="?action=logout" class="btn secondary">Logout</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Istruzioni -->
            <div class="test-section">
                <h2>📋 Come Testare il Fix</h2>

                <ol style="margin-left: 20px; line-height: 1.8;">
                    <li>Clicca su <strong>"Simula Login Admin"</strong> per creare una sessione di test</li>
                    <li>Verifica che la sessione sia attiva e che l'utente risulti admin</li>
                    <li>Clicca su <strong>"Prova Accesso Admin"</strong></li>
                    <li>Se vieni reindirizzato correttamente alla dashboard admin = ✅ Fix funziona!</li>
                    <li>Se ricevi un errore di loop = ❌ C'è ancora un problema</li>
                </ol>

                <div class="alert info" style="margin-top: 20px;">
                    <strong>Test Login Reale:</strong> Usa <strong>asamodeo@fortibyte.it</strong> / <strong>Ricord@1991</strong>
                </div>
            </div>
        </div>
    </div>
</body>
</html>