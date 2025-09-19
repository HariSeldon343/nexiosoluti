<?php
require_once '../config_v2.php';
require_once '../includes/autoload.php';
require_once '../includes/session_helper.php';
require_once '../includes/auth_v2.php';
require_once '../includes/db.php';

use Collabora\Auth\AuthenticationV2;
use Collabora\Session\SessionHelper;

// Inizializza sessione con configurazione corretta
SessionHelper::init();

// Verifica loop di reindirizzamento
if (SessionHelper::detectRedirectLoop('admin')) {
    // Mostra pagina di errore con informazioni di debug
    $debugInfo = SessionHelper::getDebugInfo();
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Errore - Loop di Reindirizzamento</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .error-box { background: white; padding: 20px; border-radius: 5px; max-width: 600px; margin: 0 auto; }
            .error-title { color: #d32f2f; margin-bottom: 20px; }
            .debug-info { background: #f0f0f0; padding: 15px; border-radius: 3px; margin-top: 20px; }
            pre { white-space: pre-wrap; font-size: 12px; }
            .action-links { margin-top: 20px; }
            .action-links a {
                display: inline-block;
                padding: 10px 20px;
                background: #2563EB;
                color: white;
                text-decoration: none;
                border-radius: 3px;
                margin-right: 10px;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1 class="error-title">Loop di Reindirizzamento Rilevato</h1>
            <p>Si è verificato un problema con il reindirizzamento. Questo può accadere quando:</p>
            <ul>
                <li>La sessione non è configurata correttamente</li>
                <li>I cookie sono disabilitati nel browser</li>
                <li>C'è un problema con i permessi utente</li>
            </ul>

            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            <div class="debug-info">
                <h3>Informazioni di Debug</h3>
                <pre><?php echo htmlspecialchars(json_encode($debugInfo, JSON_PRETTY_PRINT)); ?></pre>
            </div>
            <?php endif; ?>

            <div class="action-links">
                <a href="../index_v2.php?action=logout">Effettua nuovo login</a>
                <a href="../index_v2.php">Torna alla home</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$auth = new AuthenticationV2();

// Verifica autenticazione e ruolo admin
if (!SessionHelper::isAdmin()) {
    error_log('[ADMIN] Access denied - User not admin or not authenticated. Session: ' . session_id());
    header('Location: ../index_v2.php');
    exit;
}

// Reset contatore redirect in caso di accesso riuscito
SessionHelper::resetRedirectCount('admin');

// Log accesso riuscito
error_log('[ADMIN] Access granted for user: ' . json_encode(SessionHelper::getCurrentUser()));

// Get PDO connection for database queries
$pdo = getDbConnection();

$user = $_SESSION['user_v2'];

// Get statistics
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total tenants
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tenants");
    $totalTenants = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total files
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM files");
    $totalFiles = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Storage used (in GB)
    $stmt = $pdo->query("SELECT SUM(size) as total FROM files");
    $storageUsed = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $storageUsedGB = round($storageUsed / (1024 * 1024 * 1024), 2);

    // Recent activities
    $stmt = $pdo->query("
        SELECT al.*, u.name as user_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Users by role
    $stmt = $pdo->query("
        SELECT role, COUNT(*) as count
        FROM users
        GROUP BY role
    ");
    $usersByRole = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Handle errors gracefully
    $totalUsers = 0;
    $totalTenants = 0;
    $totalFiles = 0;
    $storageUsedGB = 0;
    $recentActivities = [];
    $usersByRole = [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Nexio Collabora</title>
    <link rel="stylesheet" href="../assets/css/auth_v2.css">
    <style>
        .admin-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            padding: 1rem;
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-table th {
            text-align: left;
            padding: 0.75rem;
            border-bottom: 2px solid var(--gray-200);
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .activity-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.875rem;
        }

        .activity-table tr:hover {
            background: var(--gray-50);
        }

        .action-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .action-badge.create {
            background: var(--success);
            color: white;
        }

        .action-badge.update {
            background: var(--info);
            color: white;
        }

        .action-badge.delete {
            background: var(--error);
            color: white;
        }

        .admin-quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .quick-link-card {
            padding: 1.5rem;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            text-align: center;
            text-decoration: none;
            color: var(--gray-700);
            transition: all var(--transition-base);
        }

        .quick-link-card:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .quick-link-card svg {
            width: 32px;
            height: 32px;
            color: var(--primary);
            margin-bottom: 0.75rem;
        }

        .quick-link-card h3 {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .quick-link-card p {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include '../components/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include '../components/header.php'; ?>

            <!-- Dashboard Content -->
            <div class="content-wrapper">
                <div class="page-header">
                    <h1>Admin Dashboard</h1>
                    <p>Panoramica del sistema e gestione amministrativa</p>
                </div>

                <!-- Admin Stats -->
                <div class="admin-stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>Utenti Totali</h3>
                            <p class="stat-value"><?php echo number_format($totalUsers); ?></p>
                            <span class="stat-change">Utenti registrati</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon green">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>Tenant Attivi</h3>
                            <p class="stat-value"><?php echo number_format($totalTenants); ?></p>
                            <span class="stat-change">Organizzazioni</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>File Totali</h3>
                            <p class="stat-value"><?php echo number_format($totalFiles); ?></p>
                            <span class="stat-change">Documenti caricati</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <h3>Spazio Utilizzato</h3>
                            <p class="stat-value"><?php echo $storageUsedGB; ?> GB</p>
                            <span class="stat-change">Storage totale</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions & Charts -->
                <div class="dashboard-grid">
                    <!-- Quick Links -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Azioni Rapide</h2>
                        </div>
                        <div class="card-body">
                            <div class="admin-quick-links">
                                <a href="users.php" class="quick-link-card">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                                    </svg>
                                    <h3>Aggiungi Utente</h3>
                                    <p>Crea nuovo account</p>
                                </a>
                                <a href="tenants.php" class="quick-link-card">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                                    </svg>
                                    <h3>Nuovo Tenant</h3>
                                    <p>Aggiungi organizzazione</p>
                                </a>
                                <a href="backup.php" class="quick-link-card">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                                    </svg>
                                    <h3>Backup</h3>
                                    <p>Esegui backup sistema</p>
                                </a>
                                <a href="settings.php" class="quick-link-card">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <h3>Impostazioni</h3>
                                    <p>Configura sistema</p>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- User Distribution Chart -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Distribuzione Utenti</h2>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="userChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h2>Attività Recenti</h2>
                        <a href="logs.php" class="link-btn">Vedi tutti i log</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recentActivities) > 0): ?>
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>Utente</th>
                                    <th>Azione</th>
                                    <th>Dettagli</th>
                                    <th>Data/Ora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivities as $activity): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($activity['user_name'] ?? 'Sistema'); ?></td>
                                    <td>
                                        <span class="action-badge <?php echo strtolower($activity['action'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($activity['action'] ?? ''); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['details'] ?? ''); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($activity['created_at'] ?? 'now')); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p>Nessuna attività recente</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- API Configuration (must load first) -->
    <script src="../assets/js/api-config.js"></script>
    <script src="../assets/js/auth_v2.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Chart.js configuration
        const userRoleData = <?php echo json_encode($usersByRole); ?>;

        if (userRoleData.length > 0) {
            const ctx = document.getElementById('userChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: userRoleData.map(item => item.role.replace('_', ' ').toUpperCase()),
                    datasets: [{
                        data: userRoleData.map(item => item.count),
                        backgroundColor: [
                            '#2563EB',
                            '#10B981',
                            '#F59E0B',
                            '#EF4444',
                            '#8B5CF6'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>