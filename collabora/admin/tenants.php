<?php
session_start();
require_once '../config.php';
require_once '../includes/auth_v2.php';

$auth = new AuthServiceV2($pdo);

// Check if user is logged in and is admin
if (!isset($_SESSION['user_v2']) || $_SESSION['user_v2']['role'] !== 'admin') {
    header('Location: ../index_v2.php');
    exit;
}

$user = $_SESSION['user_v2'];

// Get all tenants with statistics
try {
    $stmt = $pdo->query("
        SELECT t.*,
               COUNT(DISTINCT ut.user_id) as user_count,
               COUNT(DISTINCT f.id) as file_count,
               COALESCE(SUM(f.size), 0) as storage_used
        FROM tenants t
        LEFT JOIN user_tenants ut ON t.id = ut.tenant_id
        LEFT JOIN files f ON t.id = f.tenant_id
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $tenants = [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Tenant - Nexio Collabora</title>
    <link rel="stylesheet" href="../assets/css/auth_v2.css">
    <style>
        .tenant-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .tenant-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            transition: all var(--transition-base);
        }

        .tenant-card:hover {
            box-shadow: var(--card-shadow);
            transform: translateY(-2px);
        }

        .tenant-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .tenant-info h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .tenant-code {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-family: monospace;
        }

        .tenant-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .tenant-status.active {
            background: #D1FAE5;
            color: #065F46;
        }

        .tenant-status.inactive {
            background: #FEE2E2;
            color: #991B1B;
        }

        .tenant-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1rem 0;
            padding: 1rem 0;
            border-top: 1px solid var(--gray-200);
            border-bottom: 1px solid var(--gray-200);
        }

        .tenant-stat {
            text-align: center;
        }

        .tenant-stat-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .tenant-stat-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .tenant-settings {
            margin-top: 1rem;
        }

        .setting-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
        }

        .setting-label {
            font-size: 0.875rem;
            color: var(--gray-700);
        }

        .setting-value {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-900);
        }

        .tenant-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .storage-bar {
            width: 100%;
            height: 8px;
            background: var(--gray-200);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .storage-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success) 0%, var(--warning) 50%, var(--error) 100%);
            transition: width var(--transition-base);
        }

        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius-md);
        }

        .settings-grid h4 {
            grid-column: 1 / -1;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .create-tenant-card {
            background: var(--gray-50);
            border: 2px dashed var(--gray-300);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-base);
            min-height: 280px;
        }

        .create-tenant-card:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .create-tenant-card svg {
            width: 48px;
            height: 48px;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .create-tenant-card span {
            font-weight: 500;
            color: var(--gray-700);
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

            <!-- Tenants Management Content -->
            <div class="content-wrapper">
                <div class="page-header">
                    <h1>Gestione Tenant</h1>
                    <p>Gestisci le organizzazioni e le loro impostazioni</p>
                </div>

                <!-- Tenants Grid -->
                <div class="tenant-grid">
                    <!-- Create New Tenant Card -->
                    <div class="tenant-card create-tenant-card" onclick="openTenantModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Crea Nuovo Tenant</span>
                    </div>

                    <?php foreach ($tenants as $tenant):
                        $storagePercent = $tenant['max_storage'] > 0 ?
                            min(100, ($tenant['storage_used'] / ($tenant['max_storage'] * 1024 * 1024 * 1024)) * 100) : 0;
                    ?>
                    <div class="tenant-card">
                        <div class="tenant-header">
                            <div class="tenant-info">
                                <h3><?php echo htmlspecialchars($tenant['name']); ?></h3>
                                <span class="tenant-code">Codice: <?php echo htmlspecialchars($tenant['code']); ?></span>
                            </div>
                            <span class="tenant-status <?php echo $tenant['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $tenant['is_active'] ? 'Attivo' : 'Inattivo'; ?>
                            </span>
                        </div>

                        <div class="tenant-stats">
                            <div class="tenant-stat">
                                <div class="tenant-stat-value"><?php echo number_format($tenant['user_count']); ?></div>
                                <div class="tenant-stat-label">Utenti</div>
                            </div>
                            <div class="tenant-stat">
                                <div class="tenant-stat-value"><?php echo number_format($tenant['file_count']); ?></div>
                                <div class="tenant-stat-label">File</div>
                            </div>
                            <div class="tenant-stat">
                                <div class="tenant-stat-value">
                                    <?php echo round($tenant['storage_used'] / (1024 * 1024 * 1024), 2); ?> GB
                                </div>
                                <div class="tenant-stat-label">Storage</div>
                            </div>
                        </div>

                        <div class="tenant-settings">
                            <div class="setting-item">
                                <span class="setting-label">Max Utenti:</span>
                                <span class="setting-value">
                                    <?php echo $tenant['max_users'] > 0 ? number_format($tenant['max_users']) : 'Illimitati'; ?>
                                </span>
                            </div>
                            <div class="setting-item">
                                <span class="setting-label">Max Storage:</span>
                                <span class="setting-value">
                                    <?php echo $tenant['max_storage'] > 0 ? $tenant['max_storage'] . ' GB' : 'Illimitato'; ?>
                                </span>
                            </div>
                            <?php if ($tenant['max_storage'] > 0): ?>
                            <div class="storage-bar">
                                <div class="storage-fill" style="width: <?php echo $storagePercent; ?>%"></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="tenant-actions">
                            <button class="btn btn-secondary" style="flex: 1;" onclick="editTenant(<?php echo htmlspecialchars(json_encode($tenant)); ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Modifica
                            </button>
                            <button class="btn btn-secondary" style="flex: 1;" onclick="viewTenantDetails(<?php echo $tenant['id']; ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Dettagli
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tenant Modal -->
    <div id="tenantModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nuovo Tenant</h2>
                <button class="modal-close" onclick="closeTenantModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 24px; height: 24px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="tenantForm">
                <div class="modal-body">
                    <input type="hidden" id="tenantId" name="tenantId">

                    <div class="modal-form">
                        <div class="form-group">
                            <label for="tenantName">Nome Tenant *</label>
                            <input type="text" id="tenantName" name="name" required class="filter-input" style="width: 100%;">
                        </div>

                        <div class="form-group">
                            <label for="tenantCode">Codice Tenant *</label>
                            <input type="text" id="tenantCode" name="code" required class="filter-input" style="width: 100%;"
                                   pattern="[A-Z0-9]{3,10}" title="Codice alfanumerico maiuscolo (3-10 caratteri)">
                            <small style="color: var(--gray-600); font-size: 0.75rem;">
                                Codice unico alfanumerico maiuscolo (es: TENANT01)
                            </small>
                        </div>

                        <div class="settings-grid">
                            <h4>Limiti e Restrizioni</h4>

                            <div class="form-group">
                                <label for="maxUsers">Max Utenti</label>
                                <input type="number" id="maxUsers" name="max_users" min="0" class="filter-input" style="width: 100%;" placeholder="0 = Illimitati">
                            </div>

                            <div class="form-group">
                                <label for="maxStorage">Max Storage (GB)</label>
                                <input type="number" id="maxStorage" name="max_storage" min="0" class="filter-input" style="width: 100%;" placeholder="0 = Illimitato">
                            </div>
                        </div>

                        <div class="settings-grid">
                            <h4>Impostazioni</h4>

                            <div class="form-group">
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" id="tenantActive" name="is_active" checked>
                                    <span>Tenant attivo</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" id="allowWebdav" name="allow_webdav" checked>
                                    <span>Consenti WebDAV</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" id="allowSharing" name="allow_sharing" checked>
                                    <span>Consenti condivisione file</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" id="allowExternalSharing" name="allow_external_sharing">
                                    <span>Consenti condivisione esterna</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeTenantModal()">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva Tenant</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <!-- API Configuration (must load first) -->
    <script src="../assets/js/api-config.js"></script>
    <script src="../assets/js/auth_v2.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Initialize tenant management
        document.addEventListener('DOMContentLoaded', function() {
            initializeTenantManagement();
        });
    </script>
</body>
</html>