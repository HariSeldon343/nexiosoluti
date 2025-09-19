<?php
declare(strict_types=1);

/**
 * Gestione multi-tenant e isolamento dati
 */

class TenantManager {
    private static ?array $currentTenant = null;

    /**
     * Crea nuovo tenant
     */
    public static function createTenant(array $data): int {
        // Validazione
        $required = ['code', 'name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }

        // Verifica unicità codice
        if (Database::exists('tenants', ['code' => $data['code']])) {
            throw new Exception('Tenant code already exists');
        }

        // Estrai admin_email prima di inserire nel database
        $adminEmail = $data['admin_email'] ?? 'admin@' . $data['code'] . '.local';
        unset($data['admin_email']); // Rimuovi campo non esistente nella tabella

        // Valori default
        $data['status'] = $data['status'] ?? 'active';
        $data['storage_limit'] = $data['storage_limit'] ?? 10737418240; // 10GB default
        $data['storage_used'] = 0;

        // Crea tenant
        $tenantId = Database::insert('tenants', $data);

        // Crea directory upload tenant
        $uploadPath = UPLOAD_PATH . '/' . $data['code'];
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Crea utente admin default
        $adminPassword = Auth::generatePassword();
        $adminUserId = Auth::createUser([
            'tenant_id' => $tenantId,
            'username' => 'admin',
            'email' => $adminEmail,
            'password' => $adminPassword,
            'full_name' => 'Administrator',
            'role' => 'admin'
        ]);

        // Log creazione (con user_id nullable per sistema)
        self::logActivity($tenantId, $adminUserId, 'tenant_created', [
            'tenant_code' => $data['code'],
            'admin_password' => $adminPassword // Solo per primo accesso
        ]);

        return $tenantId;
    }

    /**
     * Aggiorna tenant
     */
    public static function updateTenant(int $tenantId, array $data): bool {
        // Rimuovi campi non modificabili
        unset($data['id'], $data['code'], $data['created_at']);

        if (empty($data)) {
            return false;
        }

        // Se cambia status, verifica impatto
        if (isset($data['status']) && $data['status'] !== 'active') {
            self::handleTenantStatusChange($tenantId, $data['status']);
        }

        $rows = Database::update('tenants', $data, ['id' => $tenantId]);

        // Log modifica
        self::logActivity($tenantId, Auth::getUserId(), 'tenant_updated', $data);

        return $rows > 0;
    }

    /**
     * Elimina tenant (soft delete)
     */
    public static function deleteTenant(int $tenantId): bool {
        // Non eliminare fisicamente, solo disattiva
        return self::updateTenant($tenantId, ['status' => 'suspended']);
    }

    /**
     * Ottiene tenant per codice
     */
    public static function getTenantByCode(string $code): ?array {
        return Database::selectOne(
            "SELECT * FROM tenants WHERE code = :code",
            ['code' => $code]
        );
    }

    /**
     * Ottiene tenant per ID
     */
    public static function getTenantById(int $id): ?array {
        return Database::selectOne(
            "SELECT * FROM tenants WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Identifica tenant dalla richiesta
     */
    public static function identifyTenant(): ?array {
        // 1. Check subdomain
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $parts = explode('.', $host);

        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            $tenant = self::getTenantByCode($subdomain);
            if ($tenant) {
                self::$currentTenant = $tenant;
                return $tenant;
            }
        }

        // 2. Check header X-Tenant-Code
        $headerCode = $_SERVER['HTTP_X_TENANT_CODE'] ?? null;
        if ($headerCode) {
            $tenant = self::getTenantByCode($headerCode);
            if ($tenant) {
                self::$currentTenant = $tenant;
                return $tenant;
            }
        }

        // 3. Check session
        if (isset($_SESSION['tenant_id'])) {
            $tenant = self::getTenantById($_SESSION['tenant_id']);
            if ($tenant) {
                self::$currentTenant = $tenant;
                return $tenant;
            }
        }

        // 4. Check parameter
        $paramCode = $_REQUEST['tenant'] ?? $_REQUEST['tenant_code'] ?? null;
        if ($paramCode) {
            $tenant = self::getTenantByCode($paramCode);
            if ($tenant) {
                self::$currentTenant = $tenant;
                return $tenant;
            }
        }

        return null;
    }

    /**
     * Imposta tenant corrente
     */
    public static function setCurrentTenant(array $tenant): void {
        self::$currentTenant = $tenant;
        $_SESSION['tenant_id'] = $tenant['id'];
        $_SESSION['tenant_code'] = $tenant['code'];
    }

    /**
     * Ottiene tenant corrente
     */
    public static function getCurrentTenant(): ?array {
        if (self::$currentTenant === null) {
            self::$currentTenant = self::identifyTenant();
        }
        return self::$currentTenant;
    }

    /**
     * Verifica limiti storage tenant
     */
    public static function checkStorageLimit(int $tenantId, int $additionalSize = 0): bool {
        $tenant = self::getTenantById($tenantId);

        if (!$tenant) {
            return false;
        }

        $newUsage = $tenant['storage_used'] + $additionalSize;
        return $newUsage <= $tenant['storage_limit'];
    }

    /**
     * Ottiene statistiche tenant
     */
    public static function getTenantStats(int $tenantId): array {
        $stats = [];

        // Info base tenant
        $tenant = self::getTenantById($tenantId);
        if (!$tenant) {
            return [];
        }

        $stats['tenant'] = [
            'name' => $tenant['name'],
            'code' => $tenant['code'],
            'status' => $tenant['status'],
            'created_at' => $tenant['created_at']
        ];

        // Storage
        $stats['storage'] = [
            'limit' => $tenant['storage_limit'],
            'used' => $tenant['storage_used'],
            'available' => $tenant['storage_limit'] - $tenant['storage_used'],
            'percentage' => $tenant['storage_limit'] > 0 ?
                round(($tenant['storage_used'] / $tenant['storage_limit']) * 100, 2) : 0
        ];

        // Utenti
        $stats['users'] = [
            'total' => Database::count('users', ['tenant_id' => $tenantId]),
            'active' => Database::count('users', ['tenant_id' => $tenantId, 'status' => 'active']),
            'admins' => Database::count('users', ['tenant_id' => $tenantId, 'role' => 'admin'])
        ];

        // File
        $fileStats = Database::selectOne(
            "SELECT
                COUNT(*) as total_files,
                SUM(CASE WHEN is_deleted = FALSE THEN 1 ELSE 0 END) as active_files,
                SUM(CASE WHEN is_deleted = TRUE THEN 1 ELSE 0 END) as deleted_files,
                SUM(CASE WHEN is_deleted = FALSE THEN size ELSE 0 END) as active_size,
                SUM(CASE WHEN is_deleted = TRUE THEN size ELSE 0 END) as deleted_size
             FROM files
             WHERE tenant_id = :tenant_id",
            ['tenant_id' => $tenantId]
        );

        $stats['files'] = [
            'total' => (int)$fileStats['total_files'],
            'active' => (int)$fileStats['active_files'],
            'deleted' => (int)$fileStats['deleted_files'],
            'active_size' => (int)$fileStats['active_size'],
            'deleted_size' => (int)$fileStats['deleted_size']
        ];

        // Cartelle
        $stats['folders'] = Database::count('folders', ['tenant_id' => $tenantId]);

        // Attività recenti
        $recentActivity = Database::select(
            "SELECT COUNT(*) as count, DATE(created_at) as date
             FROM activity_logs
             WHERE tenant_id = :tenant_id
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date DESC",
            ['tenant_id' => $tenantId]
        );

        $stats['recent_activity'] = $recentActivity;

        return $stats;
    }

    /**
     * Lista tutti i tenant (per super admin)
     */
    public static function listTenants(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE :search OR code LIKE :search)';
            $params['search'] = '%' . Database::escapeLike($filters['search']) . '%';
        }

        $sql = "SELECT t.*,
                (SELECT COUNT(*) FROM users WHERE tenant_id = t.id) as user_count,
                (SELECT COUNT(*) FROM files WHERE tenant_id = t.id AND is_deleted = FALSE) as file_count
                FROM tenants t";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY created_at DESC';

        return Database::select($sql, $params);
    }

    /**
     * Migra dati tra tenant
     */
    public static function migrateTenantData(int $fromTenantId, int $toTenantId, array $options = []): array {
        $results = [
            'users' => 0,
            'files' => 0,
            'folders' => 0,
            'errors' => []
        ];

        Database::beginTransaction();

        try {
            // Migra utenti (opzionale)
            if ($options['migrate_users'] ?? false) {
                $users = Database::select(
                    "SELECT * FROM users WHERE tenant_id = :tenant_id",
                    ['tenant_id' => $fromTenantId]
                );

                foreach ($users as $user) {
                    unset($user['id']);
                    $user['tenant_id'] = $toTenantId;
                    $user['username'] = $user['username'] . '_migrated';
                    $user['email'] = 'migrated_' . $user['email'];

                    try {
                        Database::insert('users', $user);
                        $results['users']++;
                    } catch (Exception $e) {
                        $results['errors'][] = 'User migration failed: ' . $e->getMessage();
                    }
                }
            }

            // Migra cartelle
            if ($options['migrate_folders'] ?? true) {
                $folders = Database::select(
                    "SELECT * FROM folders WHERE tenant_id = :tenant_id ORDER BY parent_id",
                    ['tenant_id' => $fromTenantId]
                );

                $folderMapping = [];
                foreach ($folders as $folder) {
                    $oldId = $folder['id'];
                    unset($folder['id']);
                    $folder['tenant_id'] = $toTenantId;

                    if ($folder['parent_id'] && isset($folderMapping[$folder['parent_id']])) {
                        $folder['parent_id'] = $folderMapping[$folder['parent_id']];
                    }

                    $newId = Database::insert('folders', $folder);
                    $folderMapping[$oldId] = $newId;
                    $results['folders']++;
                }
            }

            // Migra file
            if ($options['migrate_files'] ?? true) {
                $files = Database::select(
                    "SELECT * FROM files WHERE tenant_id = :tenant_id",
                    ['tenant_id' => $fromTenantId]
                );

                foreach ($files as $file) {
                    unset($file['id']);
                    $file['tenant_id'] = $toTenantId;

                    if ($file['folder_id'] && isset($folderMapping[$file['folder_id']])) {
                        $file['folder_id'] = $folderMapping[$file['folder_id']];
                    }

                    Database::insert('files', $file);
                    $results['files']++;
                }

                // Aggiorna storage usage
                $totalSize = Database::selectValue(
                    "SELECT SUM(size) FROM files WHERE tenant_id = :tenant_id AND is_deleted = FALSE",
                    ['tenant_id' => $toTenantId]
                );

                Database::update('tenants', ['storage_used' => $totalSize], ['id' => $toTenantId]);
            }

            Database::commit();

            // Log migrazione
            self::logActivity($toTenantId, Auth::getUserId(), 'tenant_migration', [
                'from_tenant' => $fromTenantId,
                'results' => $results
            ]);

        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }

        return $results;
    }

    /**
     * Backup dati tenant
     */
    public static function backupTenant(int $tenantId): string {
        $tenant = self::getTenantById($tenantId);
        if (!$tenant) {
            throw new Exception('Tenant not found');
        }

        $backupPath = LOG_PATH . '/backups';
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $filename = sprintf(
            'tenant_%s_backup_%s.json',
            $tenant['code'],
            date('Y-m-d_His')
        );
        $filepath = $backupPath . '/' . $filename;

        $data = [
            'tenant' => $tenant,
            'users' => Database::select("SELECT * FROM users WHERE tenant_id = :id", ['id' => $tenantId]),
            'folders' => Database::select("SELECT * FROM folders WHERE tenant_id = :id", ['id' => $tenantId]),
            'files' => Database::select("SELECT * FROM files WHERE tenant_id = :id", ['id' => $tenantId]),
            'backup_date' => date('Y-m-d H:i:s')
        ];

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));

        // Log backup
        self::logActivity($tenantId, Auth::getUserId(), 'tenant_backup', [
            'filename' => $filename
        ]);

        return $filepath;
    }

    /**
     * Gestisce cambio status tenant
     */
    private static function handleTenantStatusChange(int $tenantId, string $newStatus): void {
        if ($newStatus === 'suspended') {
            // Disattiva tutti gli utenti
            Database::update(
                'users',
                ['status' => 'inactive'],
                ['tenant_id' => $tenantId]
            );

            // Termina tutte le sessioni
            Database::delete('sessions', ['tenant_id' => $tenantId]);
        } elseif ($newStatus === 'active') {
            // Riattiva utenti admin
            Database::query(
                "UPDATE users SET status = 'active'
                 WHERE tenant_id = :tenant_id AND role = 'admin'",
                ['tenant_id' => $tenantId]
            );
        }
    }

    /**
     * Log attività tenant
     */
    private static function logActivity(int $tenantId, ?int $userId, string $action, array $details = []): void {
        Database::insert('activity_logs', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => 'tenant',
            'entity_id' => $tenantId,
            'details' => json_encode($details),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
}