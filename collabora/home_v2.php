<?php
/**
 * Home Page V2 - Default Landing Page After Login
 * Nexio Solution Collabora
 */

// Include authentication check
require_once __DIR__ . '/includes/SimpleAuth.php';

$auth = new SimpleAuth();

// Check if user is authenticated
if (!$auth->isAuthenticated()) {
    // Not authenticated - redirect to login
    header('Location: index_v2.php');
    exit;
}

// Get current user and tenant information
$currentUser = $auth->getCurrentUser();
$currentTenantId = $_SESSION['tenant_id'] ?? null;
$userRole = $currentUser['role'] ?? 'standard_user';

// Determine what content to show based on user role
$showFileManager = true;
$showDashboard = true;
$showAdminLinks = ($userRole === 'admin');
$showTenantSelector = ($userRole === 'special_user' || $userRole === 'admin');

// Page title
$pageTitle = 'Home - Nexio Solution Collabora';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* Home page specific styles */
        .home-container {
            display: flex;
            min-height: 100vh;
            background: #f5f5f5;
        }

        .sidebar {
            width: 250px;
            background: #111827;
            color: white;
            padding: 20px 0;
            flex-shrink: 0;
        }

        .sidebar.collapsed {
            width: 60px;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .header {
            background: white;
            padding: 15px 30px;
            border-bottom: 1px solid #e5e5e5;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4f46e5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .welcome-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .welcome-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #111827;
        }

        .welcome-subtitle {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 20px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .action-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
            text-decoration: none;
            color: #111827;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 15px;
            background: #f3f4f6;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-icon svg {
            width: 24px;
            height: 24px;
            color: #4f46e5;
        }

        .action-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .action-description {
            font-size: 14px;
            color: #6b7280;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            transition: background 0.3s ease;
            gap: 10px;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-link.active {
            background: rgba(79, 70, 229, 0.5);
            border-left: 3px solid #4f46e5;
        }

        .nav-icon {
            width: 20px;
            height: 20px;
        }

        .logout-button {
            background: #ef4444;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .logout-button:hover {
            background: #dc2626;
        }

        .tenant-info {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #92400e;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="home-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div style="padding: 0 20px;">
                <h2 style="font-size: 18px; font-weight: bold; margin-bottom: 30px;">
                    Nexio Collabora
                </h2>

                <!-- Tenant Selector (if applicable) -->
                <?php if ($showTenantSelector): ?>
                <div class="tenant-info">
                    <small>Tenant Attivo:</small><br>
                    <strong><?php echo htmlspecialchars($currentTenantId ?? 'Nessuno'); ?></strong>
                </div>
                <?php endif; ?>

                <!-- Navigation Menu -->
                <nav>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="home_v2.php" class="nav-link active">
                                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                <span>Home</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link">
                                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="files.php" class="nav-link">
                                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                </svg>
                                <span>File Manager</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#calendar" class="nav-link">
                                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span>Calendario</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#tasks" class="nav-link">
                                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                <span>Attività</span>
                            </a>
                        </li>
                        <?php if ($showAdminLinks): ?>
                        <li class="nav-item" style="margin-top: 30px;">
                            <a href="admin/index.php" class="nav-link">
                                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span>Amministrazione</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1 style="font-size: 24px; margin: 0;">Benvenuto in Nexio Collabora</h1>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($currentUser['name'] ?? $currentUser['email'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($currentUser['name'] ?? $currentUser['email']); ?></div>
                        <div style="font-size: 12px; color: #6b7280;"><?php echo htmlspecialchars($userRole); ?></div>
                    </div>
                    <form method="post" action="api/auth_simple.php" style="display: inline;">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="logout-button">Logout</button>
                    </form>
                </div>
            </div>

            <!-- Welcome Section -->
            <div class="welcome-section">
                <h2 class="welcome-title">
                    Ciao, <?php echo htmlspecialchars($currentUser['name'] ?? 'Utente'); ?>!
                </h2>
                <p class="welcome-subtitle">
                    Bentornato nel tuo spazio di lavoro collaborativo. Ecco le tue azioni rapide:
                </p>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="files.php" class="action-card">
                        <div class="action-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="action-title">File Manager</div>
                        <div class="action-description">Gestisci i tuoi documenti</div>
                    </a>

                    <a href="#calendar" class="action-card">
                        <div class="action-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="action-title">Calendario</div>
                        <div class="action-description">Visualizza eventi e appuntamenti</div>
                    </a>

                    <a href="#tasks" class="action-card">
                        <div class="action-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <div class="action-title">Attività</div>
                        <div class="action-description">Gestisci le tue attività</div>
                    </a>

                    <?php if ($showAdminLinks): ?>
                    <a href="admin/users.php" class="action-card">
                        <div class="action-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <div class="action-title">Gestione Utenti</div>
                        <div class="action-description">Amministra utenti e permessi</div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics Section -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">File Caricati</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">Eventi Oggi</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">Attività Aperte</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">Messaggi Non Letti</div>
                </div>
            </div>
        </main>
    </div>

    <!-- Load JavaScript files -->
    <script src="assets/js/post-login-config.js"></script>
    <script src="assets/js/post-login-handler.js"></script>
    <script>
        // Log that we're on the home page
        console.log('Home page loaded successfully');

        // If there's a hash in the URL, handle it
        if (window.location.hash) {
            console.log('Hash detected:', window.location.hash);
            // You could implement SPA-like navigation here if needed
        }
    </script>
</body>
</html>