<?php
// Load configuration first
require_once 'config_v2.php';

// Start session if not already started with proper configuration
if (session_status() === PHP_SESSION_NONE) {
    // Configure session parameters for subfolder installation
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => SESSION_PATH,
        'domain' => '',
        'secure' => SESSION_SECURE,
        'httponly' => SESSION_HTTPONLY,
        'samesite' => SESSION_SAMESITE
    ]);

    // Use custom session name
    session_name(SESSION_NAME);
    session_start();

    // Log session details for debugging
    error_log('[INDEX_V2] Session started - ID: ' . session_id() . ', Path: ' . SESSION_PATH);
}

require_once 'includes/autoload.php';
require_once 'includes/auth_v2.php';

use Collabora\Auth\AuthenticationV2;

$auth = new AuthenticationV2();

// Check if user is logged in - check both v2 and legacy session formats
$isLoggedIn = isset($_SESSION['user_v2']) || isset($_SESSION['user']);
$user = $isLoggedIn ? ($_SESSION['user_v2'] ?? $_SESSION['user']) : null;

// If user is logged in, redirect to appropriate page
if ($isLoggedIn && !isset($_GET['action'])) {
    // Redirect based on user role
    if ($user && isset($user['role'])) {
        switch($user['role']) {
            case 'admin':
                header('Location: admin/index.php');
                exit;
            case 'special_user':
            case 'standard_user':
            default:
                header('Location: home_v2.php'); // Updated to use home_v2.php
                exit;
        }
    } else {
        // Default redirect if role is not set
        header('Location: home_v2.php'); // Updated to use home_v2.php
        exit;
    }
}

// If not logged in and not on login page, show login form
if (!$isLoggedIn && !isset($_GET['action'])) {
    $_GET['action'] = 'login';
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index_v2.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexio Collabora - <?php echo $isLoggedIn ? 'Dashboard' : 'Accesso'; ?></title>
    <link rel="stylesheet" href="assets/css/auth_v2.css">
</head>
<body>
    <?php if (!$isLoggedIn): ?>
    <!-- Login Page -->
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <div class="logo">
                    <svg class="logo-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h1>Nexio Collabora</h1>
                </div>
                <p>Accedi al tuo account</p>
            </div>

            <form id="loginForm" class="auth-form">
                <div class="form-group">
                    <label for="username">Email</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                        <input type="email" id="username" name="email" autocomplete="username email" required placeholder="nome@azienda.com">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        <input type="password" id="password" name="password" autocomplete="current-password" required placeholder="••••••••">
                        <button type="button" class="toggle-password">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg class="eye-off-icon hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="remember">
                        <span>Ricordami</span>
                    </label>
                    <a href="#" class="forgot-link">Password dimenticata?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <span>Accedi</span>
                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75" />
                    </svg>
                </button>
            </form>
        </div>
    </div>

    <?php else: ?>
    <!-- Dashboard -->
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include 'components/header.php'; ?>

            <!-- Dashboard Content -->
            <div class="content-wrapper">
                <div class="page-header">
                    <h1>Dashboard</h1>
                    <p>Benvenuto, <?php echo htmlspecialchars($user['name']); ?></p>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>File Totali</h3>
                            <p class="stat-value">0</p>
                            <span class="stat-change positive">+0% questo mese</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon green">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>Utenti Attivi</h3>
                            <p class="stat-value">0</p>
                            <span class="stat-change">Nessuna variazione</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>Spazio Utilizzato</h3>
                            <p class="stat-value">0 GB</p>
                            <span class="stat-change">di 100 GB totali</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>Attività Recenti</h3>
                            <p class="stat-value">0</p>
                            <span class="stat-change">Ultime 24 ore</span>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity & Quick Actions -->
                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header">
                            <h2>Attività Recenti</h2>
                            <a href="#" class="link-btn">Vedi tutte</a>
                        </div>
                        <div class="card-body">
                            <div class="activity-list">
                                <div class="empty-state">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p>Nessuna attività recente</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2>Azioni Rapide</h2>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">
                                <button class="quick-action-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                                    </svg>
                                    <span>Carica File</span>
                                </button>
                                <button class="quick-action-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    <span>Nuova Cartella</span>
                                </button>
                                <button class="quick-action-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                    </svg>
                                    <span>Invita Utente</span>
                                </button>
                                <button class="quick-action-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
                                    </svg>
                                    <span>Condividi</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <!-- Error Display Container (for non-toast errors) -->
    <div id="error-display" class="error-display hidden">
        <div class="error-content">
            <div class="error-header">
                <svg class="error-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <h3 class="error-title">Errore</h3>
                <button class="error-close" onclick="document.getElementById('error-display').classList.add('hidden')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="error-message"></p>
            <div class="error-details hidden">
                <pre class="error-debug"></pre>
            </div>
            <div class="error-actions">
                <button class="btn btn-secondary" onclick="location.reload()">Ricarica Pagina</button>
                <button class="btn btn-primary" onclick="document.getElementById('error-display').classList.add('hidden')">Chiudi</button>
            </div>
        </div>
    </div>

    <!-- Debug Mode Toggle (bottom right corner) -->
    <div id="debug-toggle" class="debug-toggle" title="Toggle Debug Mode">
        <button onclick="toggleDebugMode()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 12.75c1.148 0 2.278.08 3.383.237a1.037 1.037 0 01.834.82c.044.282.044.566 0 .849a1.037 1.037 0 01-.834.82c-1.105.157-2.235.236-3.383.236-1.148 0-2.278-.08-3.383-.237a1.037 1.037 0 01-.834-.82 2.282 2.282 0 010-.848 1.037 1.037 0 01.834-.821A41.699 41.699 0 0112 12.75zm0 0c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-5.272 0 3.745 3.745 0 01-1.043-3.296A4.124 4.124 0 011.5 12.75c0-2.278 1.843-4.125 4.125-4.125h.458m6.917 0v-.75a3.375 3.375 0 00-3.375-3.375h-.917m6.917 4.125a5.625 5.625 0 011.5 3.825m-7.5-3.825h.459c2.278 0 4.125 1.847 4.125 4.125a4.125 4.125 0 01-1.55 3.218 3.745 3.745 0 011.043 3.296 3.745 3.745 0 005.272 0 3.745 3.745 0 001.043-3.296A4.124 4.124 0 0022.5 12.75c0-2.278-1.843-4.125-4.125-4.125h-.458m-6.917 0V7.875a3.375 3.375 0 013.375-3.375h.917" />
            </svg>
        </button>
        <span class="debug-status"></span>
    </div>

    <!-- Scripts -->
    <!-- API Configuration (must load first) -->
    <script src="assets/js/api-config.js"></script>
    <!-- Error Handler -->
    <script src="assets/js/error-handler.js"></script>
    <!-- Post-Login Configuration and Handler (must load before auth_v2.js) -->
    <script src="assets/js/post-login-config.js"></script>
    <script src="assets/js/post-login-handler.js"></script>
    <!-- Authentication System -->
    <script src="assets/js/auth_v2.js"></script>

    <!-- Debug Mode Script -->
    <script>
        function toggleDebugMode() {
            const isDebug = localStorage.getItem('debugMode') === 'true';
            const newState = !isDebug;

            if (window.ErrorHandler) {
                window.ErrorHandler.setDebugMode(newState);
            }

            localStorage.setItem('debugMode', newState ? 'true' : 'false');

            const statusEl = document.querySelector('.debug-status');
            if (statusEl) {
                statusEl.textContent = newState ? 'Debug ON' : '';
                statusEl.style.display = newState ? 'block' : 'none';
            }

            const toggle = document.getElementById('debug-toggle');
            if (toggle) {
                toggle.classList.toggle('active', newState);
            }

            // Show notification
            if (typeof showToast === 'function') {
                showToast('info', 'Debug Mode', newState ? 'Debug mode attivato' : 'Debug mode disattivato');
            }
        }

        // Initialize debug status on load
        document.addEventListener('DOMContentLoaded', function() {
            const isDebug = localStorage.getItem('debugMode') === 'true';
            const statusEl = document.querySelector('.debug-status');
            const toggle = document.getElementById('debug-toggle');

            if (statusEl) {
                statusEl.textContent = isDebug ? 'Debug ON' : '';
                statusEl.style.display = isDebug ? 'block' : 'none';
            }

            if (toggle) {
                toggle.classList.toggle('active', isDebug);
            }
        });
    </script>
</body>
</html>