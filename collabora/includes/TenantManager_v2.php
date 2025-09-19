<?php declare(strict_types=1);

/**
 * Tenant Manager V2
 * Gestione tenant senza codice obbligatorio
 *
 * @author Nexiosolution
 * @version 2.0.0
 * @since 2025-01-17
 */

namespace Collabora\Tenants;

use PDO;
use PDOException;
use Exception;
use InvalidArgumentException;
use Collabora\Auth\AuthenticationV2;
use Collabora\Auth\UserRole;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_v2.php';

/**
 * Enum per lo stato del tenant
 */
enum TenantStatus: string {
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case ARCHIVED = 'archived';
}

/**
 * Enum per i tier di sottoscrizione
 */
enum SubscriptionTier: string {
    case FREE = 'free';
    case STARTER = 'starter';
    case PROFESSIONAL = 'professional';
    case ENTERPRISE = 'enterprise';
}

/**
 * Classe per la gestione dei tenant
 */
class TenantManagerV2 {
    private PDO $db;
    private AuthenticationV2 $auth;
    private ?int $currentUserId = null;
    private ?int $currentTenantId = null;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->db = getDbConnection();
        $this->auth = new AuthenticationV2();

        $currentUser = $this->auth->getCurrentUser();
        if ($currentUser) {
            $this->currentUserId = (int)$currentUser['id'];
            $this->currentTenantId = $this->auth->getCurrentTenantId();
        }
    }

    /**
     * Crea un nuovo tenant
     *
     * @param array $data Dati del tenant
     * @return array Tenant creato
     * @throws Exception Se la creazione fallisce
     */
    public function createTenant(array $data): array {
        // Solo admin possono creare tenant
        if (!$this->auth->hasPermission('tenants.create')) {
            throw new Exception('Non hai i permessi per creare tenant');
        }

        // Valida dati
        $this->validateTenantData($data, true);

        try {
            $this->db->beginTransaction();

            // Genera codice univoco se non fornito
            $code = null;
            if (!empty($data['code'])) {
                $code = $this->sanitizeTenantCode($data['code']);
                if ($this->tenantCodeExists($code)) {
                    throw new Exception('Codice tenant già esistente');
                }
            }

            // Verifica unicità dominio
            if (!empty($data['domain'])) {
                if ($this->domainExists($data['domain'])) {
                    throw new Exception('Dominio già registrato');
                }
            }

            // Inserisci tenant
            $stmt = $this->db->prepare("
                INSERT INTO tenants (
                    code, name, domain, status, settings,
                    storage_quota_gb, max_users, subscription_tier,
                    subscription_expires_at, created_at
                ) VALUES (
                    :code, :name, :domain, :status, :settings,
                    :storage_quota, :max_users, :subscription_tier,
                    :subscription_expires, NOW()
                )
            ");

            $settings = $this->prepareSettings($data['settings'] ?? []);

            $stmt->execute([
                'code' => $code,
                'name' => trim($data['name']),
                'domain' => $data['domain'] ?? null,
                'status' => $data['status'] ?? TenantStatus::ACTIVE->value,
                'settings' => json_encode($settings),
                'storage_quota' => $data['storage_quota_gb'] ?? 100,
                'max_users' => $data['max_users'] ?? null,
                'subscription_tier' => $data['subscription_tier'] ?? SubscriptionTier::FREE->value,
                'subscription_expires' => $data['subscription_expires_at'] ?? null
            ]);

            $tenantId = (int)$this->db->lastInsertId();

            // Crea directory per il tenant
            $this->createTenantDirectories($tenantId);

            // Se specificato, associa un owner
            if (!empty($data['owner_id'])) {
                $this->assignOwner($tenantId, (int)$data['owner_id']);
            }

            // Log attività
            $this->logActivity('tenant.create', $tenantId, [
                'name' => $data['name'],
                'created_by' => $this->currentUserId
            ]);

            $this->db->commit();

            return $this->getTenantById($tenantId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Aggiorna un tenant esistente
     *
     * @param int $tenantId ID del tenant
     * @param array $data Dati da aggiornare
     * @return array Tenant aggiornato
     * @throws Exception Se l'aggiornamento fallisce
     */
    public function updateTenant(int $tenantId, array $data): array {
        // Verifica permessi
        if (!$this->canManageTenant($tenantId)) {
            throw new Exception('Non hai i permessi per modificare questo tenant');
        }

        // Recupera tenant esistente
        $existingTenant = $this->getTenantById($tenantId);
        if (!$existingTenant) {
            throw new Exception('Tenant non trovato');
        }

        try {
            $this->db->beginTransaction();

            // Prepara campi da aggiornare
            $updates = [];
            $params = ['id' => $tenantId];

            // Campi modificabili
            $allowedFields = ['name', 'domain', 'status', 'settings', 'storage_quota_gb', 'max_users'];

            // Admin possono modificare anche sottoscrizione
            if ($this->auth->isAdmin()) {
                $allowedFields = array_merge($allowedFields, ['code', 'subscription_tier', 'subscription_expires_at']);
            }

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    // Validazioni speciali
                    if ($field === 'code' && !empty($data[$field])) {
                        $code = $this->sanitizeTenantCode($data[$field]);
                        if ($this->tenantCodeExists($code, $tenantId)) {
                            throw new Exception('Codice tenant già in uso');
                        }
                        $params[$field] = $code;
                    } elseif ($field === 'domain' && !empty($data[$field])) {
                        if ($this->domainExists($data[$field], $tenantId)) {
                            throw new Exception('Dominio già in uso');
                        }
                        $params[$field] = $data[$field];
                    } elseif ($field === 'settings') {
                        $params[$field] = json_encode($this->prepareSettings($data[$field]));
                    } else {
                        $params[$field] = $data[$field];
                    }

                    $updates[] = "$field = :$field";
                }
            }

            // Esegui aggiornamento
            if (!empty($updates)) {
                $updates[] = "updated_at = NOW()";

                $sql = "UPDATE tenants SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            // Log attività
            $changes = array_diff_assoc($data, (array)$existingTenant);
            if (!empty($changes)) {
                $this->logActivity('tenant.update', $tenantId, [
                    'changes' => array_keys($changes),
                    'updated_by' => $this->currentUserId
                ]);
            }

            $this->db->commit();

            return $this->getTenantById($tenantId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina un tenant (soft delete)
     *
     * @param int $tenantId ID del tenant
     * @return bool Successo
     * @throws Exception Se l'eliminazione fallisce
     */
    public function deleteTenant(int $tenantId): bool {
        // Solo admin possono eliminare tenant
        if (!$this->auth->hasPermission('tenants.delete')) {
            throw new Exception('Non hai i permessi per eliminare tenant');
        }

        // Verifica esistenza
        $tenant = $this->getTenantById($tenantId);
        if (!$tenant) {
            throw new Exception('Tenant non trovato');
        }

        try {
            $this->db->beginTransaction();

            // Soft delete
            $stmt = $this->db->prepare("
                UPDATE tenants
                SET deleted_at = NOW(),
                    status = 'archived',
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute(['id' => $tenantId]);

            // Disattiva tutti gli utenti del tenant
            $stmt = $this->db->prepare("
                UPDATE users u
                JOIN user_tenant_associations uta ON uta.user_id = u.id
                SET u.status = 'inactive'
                WHERE uta.tenant_id = :tenant_id
            ");
            $stmt->execute(['tenant_id' => $tenantId]);

            // Log attività
            $this->logActivity('tenant.delete', $tenantId, [
                'deleted_by' => $this->currentUserId,
                'tenant_name' => $tenant['name']
            ]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Ottiene un tenant per ID
     *
     * @param int $tenantId ID del tenant
     * @return array|null Dati del tenant
     */
    public function getTenantById(int $tenantId): ?array {
        // Verifica permessi di visualizzazione
        if (!$this->canViewTenant($tenantId)) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT t.*,
                   COUNT(DISTINCT uta.user_id) as user_count,
                   (t.storage_used_bytes / 1073741824) as storage_used_gb
            FROM tenants t
            LEFT JOIN user_tenant_associations uta ON uta.tenant_id = t.id
            WHERE t.id = :id
              AND t.deleted_at IS NULL
            GROUP BY t.id
        ");

        $stmt->execute(['id' => $tenantId]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tenant) {
            // Decodifica settings
            if ($tenant['settings']) {
                $tenant['settings'] = json_decode($tenant['settings'], true);
            }

            // Aggiungi statistiche
            $tenant['storage_percentage'] = $tenant['storage_quota_gb'] > 0
                ? round(($tenant['storage_used_gb'] / $tenant['storage_quota_gb']) * 100, 2)
                : 0;

            // Per admin, aggiungi info owner
            if ($this->auth->isAdmin()) {
                $tenant['owner'] = $this->getTenantOwner($tenantId);
            }
        }

        return $tenant ?: null;
    }

    /**
     * Cerca tenant con filtri
     *
     * @param array $filters Filtri di ricerca
     * @param int $page Pagina
     * @param int $limit Elementi per pagina
     * @return array Risultati paginati
     */
    public function searchTenants(array $filters = [], int $page = 1, int $limit = 20): array {
        $where = ['t.deleted_at IS NULL'];
        $params = [];

        // Se non admin, mostra solo i tenant associati
        if (!$this->auth->isAdmin()) {
            $where[] = "EXISTS (
                SELECT 1 FROM user_tenant_associations uta
                WHERE uta.tenant_id = t.id AND uta.user_id = :user_id
            )";
            $params['user_id'] = $this->currentUserId;
        }

        // Applica filtri
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(t.name LIKE :search OR t.code LIKE :search OR t.domain LIKE :search)";
            $params['search'] = $search;
        }

        if (!empty($filters['status'])) {
            $where[] = "t.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['subscription_tier'])) {
            $where[] = "t.subscription_tier = :tier";
            $params['tier'] = $filters['subscription_tier'];
        }

        // Conta totale
        $countSql = "SELECT COUNT(DISTINCT t.id) FROM tenants t WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Calcola paginazione
        $offset = ($page - 1) * $limit;
        $totalPages = (int)ceil($total / $limit);

        // Query principale
        $sql = "
            SELECT t.id, t.code, t.name, t.domain, t.status,
                   t.subscription_tier, t.subscription_expires_at,
                   t.storage_quota_gb, (t.storage_used_bytes / 1073741824) as storage_used_gb,
                   t.created_at, t.updated_at,
                   COUNT(DISTINCT uta.user_id) as user_count
            FROM tenants t
            LEFT JOIN user_tenant_associations uta ON uta.tenant_id = t.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY t.id
            ORDER BY t.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'tenants' => $tenants,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages
            ]
        ];
    }

    /**
     * Ottiene i tenant disponibili per l'utente corrente
     *
     * @return array Lista tenant
     */
    public function getUserAvailableTenants(): array {
        if (!$this->currentUserId) {
            return [];
        }

        // Admin vede tutti i tenant
        if ($this->auth->isAdmin()) {
            $stmt = $this->db->prepare("
                SELECT t.id, t.code, t.name, t.domain, t.status,
                       'admin' as role_in_tenant, false as is_primary
                FROM tenants t
                WHERE t.status = 'active'
                  AND t.deleted_at IS NULL
                ORDER BY t.name
            ");
            $stmt->execute();
        } else {
            // Altri utenti vedono solo i tenant associati
            $stmt = $this->db->prepare("
                SELECT t.id, t.code, t.name, t.domain, t.status,
                       uta.role_in_tenant, uta.is_primary, uta.last_accessed_at
                FROM user_tenant_associations uta
                JOIN tenants t ON t.id = uta.tenant_id
                WHERE uta.user_id = :user_id
                  AND t.status = 'active'
                  AND t.deleted_at IS NULL
                ORDER BY uta.is_primary DESC, uta.last_accessed_at DESC, t.name
            ");
            $stmt->execute(['user_id' => $this->currentUserId]);
        }

        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Aggiungi flag per tenant corrente
        foreach ($tenants as &$tenant) {
            $tenant['is_current'] = ($tenant['id'] == $this->currentTenantId);
        }

        return $tenants;
    }

    /**
     * Cambia il tenant attivo per l'utente corrente
     *
     * @param int $tenantId ID del nuovo tenant
     * @return bool Successo
     * @throws Exception Se il cambio fallisce
     */
    public function switchToTenant(int $tenantId): bool {
        if (!$this->currentUserId) {
            throw new Exception('Utente non autenticato');
        }

        // Usa il metodo dell'auth per cambiare tenant
        return $this->auth->switchTenant($tenantId);
    }

    /**
     * Assegna un owner a un tenant
     *
     * @param int $tenantId ID tenant
     * @param int $userId ID utente
     * @return bool Successo
     */
    public function assignOwner(int $tenantId, int $userId): bool {
        // Solo admin possono assegnare owner
        if (!$this->auth->isAdmin()) {
            throw new Exception('Solo gli amministratori possono assegnare owner');
        }

        try {
            // Verifica che l'utente esista
            $stmt = $this->db->prepare("
                SELECT id FROM users
                WHERE id = :user_id AND deleted_at IS NULL
            ");
            $stmt->execute(['user_id' => $userId]);

            if (!$stmt->fetch()) {
                throw new Exception('Utente non trovato');
            }

            // Crea o aggiorna associazione come owner
            $stmt = $this->db->prepare("
                INSERT INTO user_tenant_associations
                    (user_id, tenant_id, role_in_tenant, is_primary, invited_by)
                VALUES
                    (:user_id, :tenant_id, 'owner', true, :invited_by)
                ON DUPLICATE KEY UPDATE
                    role_in_tenant = 'owner',
                    is_primary = true
            ");

            $result = $stmt->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'invited_by' => $this->currentUserId
            ]);

            // Log attività
            $this->logActivity('tenant.owner.assign', $tenantId, [
                'owner_id' => $userId,
                'assigned_by' => $this->currentUserId
            ]);

            return $result;

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Ottiene l'owner di un tenant
     *
     * @param int $tenantId ID tenant
     * @return array|null Dati owner
     */
    private function getTenantOwner(int $tenantId): ?array {
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.first_name, u.last_name,
                   uta.joined_at as owner_since
            FROM user_tenant_associations uta
            JOIN users u ON u.id = uta.user_id
            WHERE uta.tenant_id = :tenant_id
              AND uta.role_in_tenant = 'owner'
              AND u.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute(['tenant_id' => $tenantId]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);

        return $owner ?: null;
    }

    /**
     * Aggiorna lo spazio utilizzato da un tenant
     *
     * @param int $tenantId ID tenant
     * @param int $bytesChange Cambio in bytes (positivo o negativo)
     * @return bool Successo
     */
    public function updateStorageUsage(int $tenantId, int $bytesChange): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE tenants
                SET storage_used_bytes = GREATEST(0, storage_used_bytes + :change),
                    updated_at = NOW()
                WHERE id = :tenant_id
            ");

            return $stmt->execute([
                'tenant_id' => $tenantId,
                'change' => $bytesChange
            ]);

        } catch (PDOException $e) {
            error_log("Storage update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se l'utente può gestire un tenant
     *
     * @param int $tenantId ID tenant
     * @return bool True se può gestire
     */
    private function canManageTenant(int $tenantId): bool {
        // Admin possono gestire tutti
        if ($this->auth->isAdmin()) {
            return true;
        }

        // Verifica se è owner o admin del tenant
        if (!$this->currentUserId) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT role_in_tenant
            FROM user_tenant_associations
            WHERE user_id = :user_id
              AND tenant_id = :tenant_id
              AND role_in_tenant IN ('owner', 'admin')
        ");

        $stmt->execute([
            'user_id' => $this->currentUserId,
            'tenant_id' => $tenantId
        ]);

        return (bool)$stmt->fetch();
    }

    /**
     * Verifica se l'utente può visualizzare un tenant
     *
     * @param int $tenantId ID tenant
     * @return bool True se può visualizzare
     */
    private function canViewTenant(int $tenantId): bool {
        // Admin vedono tutto
        if ($this->auth->isAdmin()) {
            return true;
        }

        // Verifica associazione
        if (!$this->currentUserId) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT 1
            FROM user_tenant_associations
            WHERE user_id = :user_id AND tenant_id = :tenant_id
        ");

        $stmt->execute([
            'user_id' => $this->currentUserId,
            'tenant_id' => $tenantId
        ]);

        return (bool)$stmt->fetch();
    }

    /**
     * Crea le directory per un tenant
     *
     * @param int $tenantId ID tenant
     */
    private function createTenantDirectories(int $tenantId): void {
        $baseDir = dirname(__DIR__) . '/uploads/tenants/' . $tenantId;
        $directories = [
            $baseDir,
            $baseDir . '/files',
            $baseDir . '/temp',
            $baseDir . '/thumbnails'
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Prepara le impostazioni del tenant
     *
     * @param array $settings Impostazioni fornite
     * @return array Impostazioni preparate
     */
    private function prepareSettings(array $settings): array {
        // Impostazioni predefinite
        $defaults = [
            'locale' => 'it_IT',
            'timezone' => 'Europe/Rome',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'max_file_size_mb' => 100,
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'png', 'zip'],
            'enable_versioning' => true,
            'enable_trash' => true,
            'trash_retention_days' => 30,
            'enable_2fa' => false,
            'session_timeout_minutes' => 60
        ];

        return array_merge($defaults, $settings);
    }

    /**
     * Sanitizza il codice del tenant
     *
     * @param string $code Codice da sanitizzare
     * @return string Codice sanitizzato
     */
    private function sanitizeTenantCode(string $code): string {
        // Rimuovi caratteri non alfanumerici, mantieni solo lettere, numeri, trattini e underscore
        $code = preg_replace('/[^a-zA-Z0-9_-]/', '', $code);
        return strtolower(substr($code, 0, 20));
    }

    /**
     * Verifica se un codice tenant esiste già
     *
     * @param string $code Codice da verificare
     * @param int|null $excludeTenantId ID tenant da escludere
     * @return bool True se esiste
     */
    private function tenantCodeExists(string $code, ?int $excludeTenantId = null): bool {
        $sql = "SELECT COUNT(*) FROM tenants WHERE code = :code AND deleted_at IS NULL";
        $params = ['code' => $code];

        if ($excludeTenantId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeTenantId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica se un dominio esiste già
     *
     * @param string $domain Dominio da verificare
     * @param int|null $excludeTenantId ID tenant da escludere
     * @return bool True se esiste
     */
    private function domainExists(string $domain, ?int $excludeTenantId = null): bool {
        $sql = "SELECT COUNT(*) FROM tenants WHERE domain = :domain AND deleted_at IS NULL";
        $params = ['domain' => $domain];

        if ($excludeTenantId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeTenantId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Valida i dati del tenant
     *
     * @param array $data Dati da validare
     * @param bool $isCreate True se creazione
     * @throws InvalidArgumentException Se i dati non sono validi
     */
    private function validateTenantData(array $data, bool $isCreate = false): void {
        // Nome obbligatorio
        if ($isCreate || isset($data['name'])) {
            if (empty($data['name'])) {
                throw new InvalidArgumentException('Il nome del tenant è obbligatorio');
            }
            if (strlen($data['name']) > 255) {
                throw new InvalidArgumentException('Il nome del tenant è troppo lungo (max 255 caratteri)');
            }
        }

        // Valida codice se fornito
        if (!empty($data['code'])) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['code'])) {
                throw new InvalidArgumentException('Il codice tenant può contenere solo lettere, numeri, trattini e underscore');
            }
            if (strlen($data['code']) > 20) {
                throw new InvalidArgumentException('Il codice tenant è troppo lungo (max 20 caratteri)');
            }
        }

        // Valida dominio se fornito
        if (!empty($data['domain'])) {
            if (!filter_var('http://' . $data['domain'], FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Dominio non valido');
            }
        }

        // Valida stato
        if (isset($data['status'])) {
            $validStatuses = [
                TenantStatus::ACTIVE->value,
                TenantStatus::SUSPENDED->value,
                TenantStatus::ARCHIVED->value
            ];
            if (!in_array($data['status'], $validStatuses)) {
                throw new InvalidArgumentException('Stato tenant non valido');
            }
        }

        // Valida tier sottoscrizione
        if (isset($data['subscription_tier'])) {
            $validTiers = [
                SubscriptionTier::FREE->value,
                SubscriptionTier::STARTER->value,
                SubscriptionTier::PROFESSIONAL->value,
                SubscriptionTier::ENTERPRISE->value
            ];
            if (!in_array($data['subscription_tier'], $validTiers)) {
                throw new InvalidArgumentException('Tier di sottoscrizione non valido');
            }
        }

        // Valida quota storage
        if (isset($data['storage_quota_gb'])) {
            if (!is_numeric($data['storage_quota_gb']) || $data['storage_quota_gb'] < 1) {
                throw new InvalidArgumentException('Quota storage non valida');
            }
        }

        // Valida max utenti
        if (isset($data['max_users'])) {
            if (!is_numeric($data['max_users']) || $data['max_users'] < 1) {
                throw new InvalidArgumentException('Numero massimo utenti non valido');
            }
        }
    }

    /**
     * Log attività tenant
     *
     * @param string $action Azione eseguita
     * @param int $entityId ID entità
     * @param array|null $metadata Metadati aggiuntivi
     */
    private function logActivity(string $action, int $entityId, ?array $metadata = null): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs
                    (user_id, tenant_id, action, entity_type, entity_id, metadata, ip_address, user_agent, session_id)
                VALUES
                    (:user_id, :tenant_id, :action, 'tenant', :entity_id, :metadata, :ip, :user_agent, :session_id)
            ");

            $stmt->execute([
                'user_id' => $this->currentUserId,
                'tenant_id' => $this->currentTenantId,
                'action' => $action,
                'entity_id' => $entityId,
                'metadata' => $metadata ? json_encode($metadata) : null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'session_id' => session_id()
            ]);
        } catch (PDOException $e) {
            error_log("Activity log error: " . $e->getMessage());
        }
    }
}