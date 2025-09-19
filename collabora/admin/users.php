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

// Get all users with their tenants
try {
    $stmt = $pdo->query("
        SELECT u.*, GROUP_CONCAT(t.name SEPARATOR ', ') as tenant_names
        FROM users u
        LEFT JOIN user_tenants ut ON u.id = ut.user_id
        LEFT JOIN tenants t ON ut.tenant_id = t.id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all tenants for the dropdown
    $stmt = $pdo->query("SELECT * FROM tenants ORDER BY name");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $users = [];
    $tenants = [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Utenti - Nexio Collabora</title>
    <link rel="stylesheet" href="../assets/css/auth_v2.css">
    <style>
        .page-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .search-filters {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-input {
            padding: 0.625rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            min-width: 200px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .data-table th {
            background: var(--gray-50);
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--gray-700);
            border-bottom: 1px solid var(--card-border);
        }

        .data-table td {
            padding: 0.75rem;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .data-table tr:hover {
            background: var(--gray-50);
        }

        .user-avatar-cell {
            width: 36px;
            height: 36px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .role-badge.admin {
            background: var(--error);
            color: white;
        }

        .role-badge.special_user {
            background: var(--warning);
            color: white;
        }

        .role-badge.standard_user {
            background: var(--info);
            color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge.active {
            background: #D1FAE5;
            color: #065F46;
        }

        .status-badge.inactive {
            background: #FEE2E2;
            color: #991B1B;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.375rem;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray-600);
            border-radius: var(--radius-sm);
            transition: all var(--transition-base);
        }

        .action-btn:hover {
            background: var(--gray-100);
            color: var(--gray-900);
        }

        .action-btn svg {
            width: 18px;
            height: 18px;
        }

        .action-btn.delete:hover {
            background: var(--error);
            color: white;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray-400);
            padding: 0.25rem;
        }

        .modal-close:hover {
            color: var(--gray-600);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--card-border);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .pagination-btn {
            padding: 0.5rem 0.75rem;
            background: white;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--gray-700);
            transition: all var(--transition-base);
        }

        .pagination-btn:hover {
            background: var(--gray-50);
        }

        .pagination-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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

            <!-- Users Management Content -->
            <div class="content-wrapper">
                <div class="page-header">
                    <h1>Gestione Utenti</h1>
                    <p>Gestisci gli account utente e le loro autorizzazioni</p>
                </div>

                <!-- Page Actions -->
                <div class="page-actions">
                    <div class="search-filters">
                        <input type="text" id="searchInput" class="filter-input" placeholder="Cerca utenti...">
                        <select id="roleFilter" class="filter-input">
                            <option value="">Tutti i ruoli</option>
                            <option value="admin">Admin</option>
                            <option value="special_user">Special User</option>
                            <option value="standard_user">Standard User</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" onclick="openUserModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Nuovo Utente</span>
                    </button>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-body" style="padding: 0;">
                        <table class="data-table" id="usersTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;"></th>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Ruolo</th>
                                    <th>Tenant</th>
                                    <th>Stato</th>
                                    <th>Data Registrazione</th>
                                    <th style="width: 100px;">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $userData): ?>
                                <tr>
                                    <td>
                                        <div class="user-avatar-cell">
                                            <?php echo strtoupper(substr($userData['name'], 0, 2)); ?>
                                        </div>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($userData['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($userData['email']); ?></td>
                                    <td>
                                        <span class="role-badge <?php echo $userData['role']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $userData['role'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($userData['tenant_names'] ?? 'Nessuno'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $userData['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $userData['is_active'] ? 'Attivo' : 'Inattivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($userData['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn" onclick="editUser(<?php echo htmlspecialchars(json_encode($userData)); ?>)" title="Modifica">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                </svg>
                                            </button>
                                            <button class="action-btn delete" onclick="deleteUser(<?php echo $userData['id']; ?>)" title="Elimina">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if (count($users) === 0): ?>
                        <div class="empty-state" style="padding: 3rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                            <p>Nessun utente trovato</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <button class="pagination-btn" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                    </button>
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn">2</button>
                    <button class="pagination-btn">3</button>
                    <button class="pagination-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nuovo Utente</h2>
                <button class="modal-close" onclick="closeUserModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 24px; height: 24px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="userForm">
                <div class="modal-body">
                    <input type="hidden" id="userId" name="userId">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="userName">Nome *</label>
                            <input type="text" id="userName" name="name" required class="filter-input" style="width: 100%;">
                        </div>
                        <div class="form-group">
                            <label for="userEmail">Email *</label>
                            <input type="email" id="userEmail" name="email" required class="filter-input" style="width: 100%;">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="userPassword">Password *</label>
                            <input type="password" id="userPassword" name="password" class="filter-input" style="width: 100%;">
                            <small style="color: var(--gray-600); font-size: 0.75rem;">Lascia vuoto per mantenere la password attuale</small>
                        </div>
                        <div class="form-group">
                            <label for="userRole">Ruolo *</label>
                            <select id="userRole" name="role" required class="filter-input" style="width: 100%;">
                                <option value="standard_user">Standard User</option>
                                <option value="special_user">Special User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row full">
                        <div class="form-group">
                            <label>Tenant Associati</label>
                            <div class="checkbox-group">
                                <?php foreach ($tenants as $tenant): ?>
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" name="tenants[]" value="<?php echo $tenant['id']; ?>">
                                    <span><?php echo htmlspecialchars($tenant['name']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row full">
                        <div class="form-group">
                            <label class="checkbox-wrapper">
                                <input type="checkbox" id="userActive" name="is_active" checked>
                                <span>Utente attivo</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva Utente</button>
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
        // Initialize user management
        document.addEventListener('DOMContentLoaded', function() {
            initializeUserManagement();
        });
    </script>
</body>
</html>