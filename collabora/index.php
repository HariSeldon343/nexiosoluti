<?php
declare(strict_types=1);

/**
 * Entry point principale - File Manager Multi-tenant
 * Sistema completo di gestione file con isolamento tenant
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/TenantManager.php';

// Inizializza sessione
Auth::initSession();

// Crea tabelle se non esistono
try {
    Database::createTables();
} catch (Exception $e) {
    // Tabelle già esistono o altro errore non bloccante
}

// Se non loggato, mostra form login
if (!Auth::isLoggedIn()) {
    showLoginPage();
    exit;
}

// Utente loggato - mostra dashboard
showDashboard();

/**
 * Mostra pagina login
 */
function showLoginPage(): void {
    $error = $_GET['error'] ?? '';
    $expired = $_GET['expired'] ?? '';
    $csrfToken = Auth::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Nexio File Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px;
        }

        .logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo h1 {
            color: #667eea;
            font-size: 32px;
            font-weight: 700;
        }

        .logo p {
            color: #6b7280;
            font-size: 14px;
            margin-top: 8px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            color: #374151;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        button {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .alert-warning {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }

        .demo-info {
            margin-top: 30px;
            padding: 16px;
            background: #f3f4f6;
            border-radius: 8px;
            font-size: 13px;
            color: #6b7280;
        }

        .demo-info h4 {
            color: #374151;
            margin-bottom: 8px;
        }

        .demo-info code {
            background: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Monaco', 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Nexio File Manager</h1>
            <p>Sistema Multi-tenant di Gestione File</p>
        </div>

        <?php if ($expired): ?>
        <div class="alert alert-warning">
            La tua sessione è scaduta. Effettua nuovamente l'accesso.
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            Credenziali non valide. Riprova.
        </div>
        <?php endif; ?>

        <form method="post" action="api/auth.php?action=login" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="tenant_code">Codice Tenant</label>
                <input type="text" id="tenant_code" name="tenant_code" required
                       placeholder="esempio: demo" value="demo">
            </div>

            <div class="form-group">
                <label for="username">Username o Email</label>
                <input type="text" id="username" name="username" required
                       placeholder="admin" value="admin">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       placeholder="Password" value="password123">
            </div>

            <button type="submit">Accedi</button>
        </form>

        <div class="demo-info">
            <h4>Credenziali Demo:</h4>
            <p>Tenant: <code>demo</code></p>
            <p>Username: <code>admin</code></p>
            <p>Password: <code>password123</code></p>
        </div>
    </div>

    <script>
        // Gestione form login con AJAX
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const form = e.target;
            const button = form.querySelector('button');
            button.disabled = true;
            button.textContent = 'Accesso in corso...';

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    window.location.reload();
                } else {
                    window.location.href = '?error=1';
                }
            } catch (error) {
                console.error('Login error:', error);
                window.location.href = '?error=1';
            }
        });
    </script>
</body>
</html>
<?php
}

/**
 * Mostra dashboard principale
 */
function showDashboard(): void {
    $user = Auth::getCurrentUser();
    $tenant = Auth::getCurrentTenant();
    $csrfToken = Auth::generateCsrfToken();

    // Ottieni statistiche
    $fm = new FileManager();
    $stats = $fm->getStorageStats();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title>File Manager - <?= htmlspecialchars($tenant['name']) ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f9fafb;
            color: #1f2937;
        }

        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }

        .tenant-info {
            padding: 8px 16px;
            background: #eff6ff;
            border-radius: 8px;
            font-size: 14px;
            color: #1e40af;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-info {
            font-size: 14px;
            color: #6b7280;
        }

        .btn-logout {
            padding: 8px 16px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        /* Layout */
        .container {
            display: flex;
            height: calc(100vh - 65px);
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #e5e7eb;
            overflow-y: auto;
        }

        .storage-stats {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .storage-bar {
            background: #e5e7eb;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 12px 0;
        }

        .storage-used {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            transition: width 0.3s;
        }

        .storage-text {
            font-size: 12px;
            color: #6b7280;
        }

        .folder-tree {
            padding: 20px;
        }

        .folder-item {
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 6px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .folder-item:hover {
            background: #f3f4f6;
        }

        .folder-item.active {
            background: #eff6ff;
            color: #2563eb;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
        }

        /* Toolbar */
        .toolbar {
            background: white;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .toolbar-left {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
        }

        .btn-secondary {
            background: white;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #f9fafb;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 8px 12px 8px 36px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            width: 300px;
            font-size: 14px;
        }

        .search-box::before {
            content: "🔍";
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* File Grid */
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
        }

        .file-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .file-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .file-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .file-name {
            font-size: 14px;
            color: #1f2937;
            word-break: break-word;
            margin-bottom: 4px;
        }

        .file-size {
            font-size: 12px;
            color: #9ca3af;
        }

        /* Upload Zone */
        .upload-zone {
            background: white;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .upload-zone.dragover {
            border-color: #667eea;
            background: #eff6ff;
        }

        .upload-zone h3 {
            color: #4b5563;
            margin-bottom: 8px;
        }

        .upload-zone p {
            color: #9ca3af;
            font-size: 14px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-size: 20px;
            color: #1f2937;
        }

        .modal-footer {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .search-box input {
                width: 200px;
            }

            .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="sidebar-logo-icon">
                        <svg viewBox="0 0 24 24" fill="white">
                            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                        </svg>
                    </div>
                    <span class="sidebar-logo-text">Nexio Files</span>
                </div>
                <button class="sidebar-toggle">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Principale</div>
                    <div class="nav-item active" data-path="/">
                        <svg class="nav-item-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                        </svg>
                        <span class="nav-item-text">Home</span>
                    </div>
                    <div class="nav-item" data-path="/recent">
                        <svg class="nav-item-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                        </svg>
                        <span class="nav-item-text">Recenti</span>
                    </div>
                    <div class="nav-item" data-path="/shared">
                        <svg class="nav-item-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"/>
                        </svg>
                        <span class="nav-item-text">Condivisi</span>
                    </div>
                    <div class="nav-item" data-path="/trash">
                        <svg class="nav-item-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span class="nav-item-text">Cestino</span>
                        <?php if ($stats['trash_count'] > 0): ?>
                        <span class="nav-item-badge"><?= $stats['trash_count'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Cartelle</div>
                    <div class="folder-tree" id="folderTree">
                        <!-- Folders will be loaded here -->
                    </div>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="storage-indicator">
                    <div class="storage-header">
                        <span class="storage-title">Spazio utilizzato</span>
                        <span class="storage-percent"><?= $stats['percentage_used'] ?>%</span>
                    </div>
                    <div class="storage-bar">
                        <div class="storage-bar-fill" style="width: <?= $stats['percentage_used'] ?>%"></div>
                    </div>
                    <div class="storage-details">
                        <span><?= format_bytes($stats['total_used']) ?></span>
                        <span><?= format_bytes($stats['total_limit']) ?></span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Sidebar Backdrop for Mobile -->
        <div class="sidebar-backdrop"></div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="btn btn-icon mobile-menu-toggle" style="display: none;">
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <nav class="breadcrumb">
                        <!-- Breadcrumb will be generated dynamically -->
                    </nav>
                </div>
                <div class="header-right">
                    <div class="search-box">
                        <svg class="search-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
                        </svg>
                        <input type="text" class="search-input" placeholder="Cerca file..." id="searchInput">
                    </div>
                    <div class="view-toggle">
                        <button class="view-toggle-btn active" data-view="grid">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM13 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z"/>
                            </svg>
                        </button>
                        <button class="view-toggle-btn" data-view="list">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                    <button class="btn btn-icon theme-toggle" id="themeToggle">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
                        </svg>
                    </button>
                    <div class="user-menu">
                        <div class="user-avatar">
                            <?= substr($user['full_name'], 0, 1) ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <button class="btn btn-primary" id="btnUpload">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Carica
                    </button>
                    <button class="btn btn-secondary" id="btnNewFolder">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                            <path stroke="currentColor" stroke-width="2" d="M10 9v6M7 12h6"/>
                        </svg>
                        Nuova Cartella
                    </button>
                    <button class="btn btn-ghost" id="btnRefresh">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <button class="btn btn-ghost" id="btnDelete" disabled>
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
                <div class="toolbar-right">
                    <span class="selection-info" style="display: none;"></span>
                </div>
            </div>
            <!-- File Explorer -->
            <div class="file-explorer">
                <!-- Drop Zone Overlay -->
                <div class="drop-zone">
                    <div class="drop-zone-content">
                        <svg class="drop-zone-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <h2 class="drop-zone-title">Rilascia i file qui</h2>
                        <p class="drop-zone-subtitle">I file verranno caricati nella cartella corrente</p>
                    </div>
                </div>

                <!-- File Container -->
                <div class="file-container file-grid" id="fileContainer">
                    <!-- Files will be loaded here dynamically -->
                </div>
            </div>
        </main>
    </div>

    <!-- Context Menu -->
    <div class="context-menu"></div>

    <!-- Upload Modal -->
    <div class="modal hidden" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Carica File</h2>
            </div>
            <form id="uploadForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="folder_id" id="uploadFolderId" value="">
                <div style="margin-bottom: 16px;">
                    <input type="file" name="files[]" multiple required style="width: 100%;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">
                        Annulla
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Carica
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script type="module" src="assets/js/app.js"></script>
    <script>
        // Legacy compatibility layer
        const csrfToken = '<?= htmlspecialchars($csrfToken) ?>';

        // Init
        document.addEventListener('DOMContentLoaded', () => {
            loadFolderTree();
            loadFiles();
            setupDragDrop();
            setupSearch();
        });

        // Carica albero cartelle
        async function loadFolderTree() {
            try {
                const response = await fetch('api/folders.php?action=tree');
                const data = await response.json();

                if (data.success) {
                    renderFolderTree(data.tree);
                }
            } catch (error) {
                console.error('Error loading folder tree:', error);
            }
        }

        // Renderizza albero cartelle
        function renderFolderTree(tree, container = document.getElementById('folderTree'), level = 0) {
            if (level === 0) {
                // Mantieni root
                const root = container.querySelector('.folder-item');
                root.onclick = () => selectFolder(null);
            }

            tree.forEach(folder => {
                const item = document.createElement('div');
                item.className = 'folder-item';
                item.style.paddingLeft = (12 + level * 16) + 'px';
                item.dataset.folderId = folder.id;
                item.innerHTML = `📁 ${folder.name}`;
                item.onclick = () => selectFolder(folder.id);
                container.appendChild(item);

                if (folder.children && folder.children.length > 0) {
                    renderFolderTree(folder.children, container, level + 1);
                }
            });
        }

        // Seleziona cartella
        function selectFolder(folderId) {
            currentFolderId = folderId;

            // Aggiorna UI
            document.querySelectorAll('.folder-item').forEach(item => {
                item.classList.remove('active');
                if ((folderId === null && item.dataset.folderId === '') ||
                    (folderId && item.dataset.folderId == folderId)) {
                    item.classList.add('active');
                }
            });

            // Ricarica file
            loadFiles(folderId);
        }

        // Carica file
        async function loadFiles(folderId = null) {
            try {
                let url = 'api/files.php?action=list';
                if (folderId) {
                    url += `&folder_id=${folderId}`;
                }

                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    renderFiles(data.files);
                }
            } catch (error) {
                console.error('Error loading files:', error);
            }
        }

        // Renderizza file
        function renderFiles(files) {
            const grid = document.getElementById('fileGrid');
            grid.innerHTML = '';

            if (files.length === 0) {
                grid.innerHTML = '<p style="color: #9ca3af; text-align: center;">Nessun file in questa cartella</p>';
                return;
            }

            files.forEach(file => {
                const card = document.createElement('div');
                card.className = 'file-card';
                card.innerHTML = `
                    <div class="file-icon">${getFileIcon(file.extension)}</div>
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${formatBytes(file.size)}</div>
                `;
                card.onclick = () => downloadFile(file.id);
                grid.appendChild(card);
            });
        }

        // Icona file per estensione
        function getFileIcon(ext) {
            const icons = {
                pdf: '📄', doc: '📝', docx: '📝',
                xls: '📊', xlsx: '📊',
                jpg: '🖼️', jpeg: '🖼️', png: '🖼️', gif: '🖼️',
                mp3: '🎵', mp4: '🎬',
                zip: '📦', rar: '📦',
                txt: '📃', default: '📎'
            };
            return icons[ext] || icons.default;
        }

        // Formatta bytes
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        // Setup drag & drop
        function setupDragDrop() {
            const zone = document.getElementById('uploadZone');

            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                zone.classList.add('dragover');
            });

            zone.addEventListener('dragleave', () => {
                zone.classList.remove('dragover');
            });

            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                zone.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    uploadFiles(files);
                }
            });
        }

        // Upload files
        async function uploadFiles(files) {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('folder_id', currentFolderId || '');

            for (let i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
            }

            try {
                const response = await fetch('api/files.php?action=upload', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    loadFiles(currentFolderId);
                    if (data.errors && data.errors.length > 0) {
                        alert('Alcuni file non sono stati caricati:\n' +
                              data.errors.map(e => e.file + ': ' + e.error).join('\n'));
                    }
                } else {
                    alert('Errore upload: ' + (data.error || 'Errore sconosciuto'));
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('Errore durante il caricamento');
            }
        }

        // Setup ricerca
        function setupSearch() {
            let searchTimeout;
            document.getElementById('searchInput').addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    searchFiles(e.target.value);
                }, 300);
            });
        }

        // Cerca file
        async function searchFiles(query) {
            if (!query) {
                loadFiles(currentFolderId);
                return;
            }

            try {
                const response = await fetch(`api/files.php?action=search&q=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data.success) {
                    renderFiles(data.files);
                }
            } catch (error) {
                console.error('Search error:', error);
            }
        }

        // Download file
        function downloadFile(fileId) {
            window.open(`api/files.php?action=download&id=${fileId}`, '_blank');
        }

        // Crea cartella
        async function createFolder() {
            const name = prompt('Nome della nuova cartella:');
            if (!name) return;

            try {
                const response = await fetch('api/folders.php?action=create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        name: name,
                        parent_id: currentFolderId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    loadFolderTree();
                    loadFiles(currentFolderId);
                } else {
                    alert('Errore: ' + (data.error || 'Errore sconosciuto'));
                }
            } catch (error) {
                console.error('Create folder error:', error);
                alert('Errore durante la creazione della cartella');
            }
        }

        // Mostra modal upload
        function showUploadModal() {
            document.getElementById('uploadFolderId').value = currentFolderId || '';
            document.getElementById('uploadModal').classList.add('show');
        }

        // Chiudi modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Setup upload form
        document.getElementById('uploadForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);

            try {
                const response = await fetch('api/files.php?action=upload', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    closeModal('uploadModal');
                    loadFiles(currentFolderId);
                } else {
                    alert('Errore: ' + (data.error || 'Errore sconosciuto'));
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('Errore durante il caricamento');
            }
        });

        // Mostra cestino
        async function showTrash() {
            try {
                const response = await fetch('api/files.php?action=trash');
                const data = await response.json();

                if (data.success) {
                    renderFiles(data.files);
                }
            } catch (error) {
                console.error('Error loading trash:', error);
            }
        }

        // Helper function for PHP
        function format_bytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>
<?php
}

// Crea tenant e utente demo se non esistono
function setupDemoData(): void {
    try {
        // Verifica se tenant demo esiste
        $tenant = TenantManager::getTenantByCode('demo');

        if (!$tenant) {
            // Crea tenant demo
            $tenantId = TenantManager::createTenant([
                'code' => 'demo',
                'name' => 'Demo Tenant',
                'domain' => 'demo.localhost',
                'admin_email' => 'admin@demo.local'
            ]);

            // Crea utente admin con password nota
            Database::update('users',
                ['password' => password_hash('password123', PASSWORD_ALGO, PASSWORD_OPTIONS)],
                ['tenant_id' => $tenantId, 'username' => 'admin']
            );
        }
    } catch (Exception $e) {
        // Ignora errori - demo data è opzionale
    }
}

// Setup iniziale
setupDemoData();