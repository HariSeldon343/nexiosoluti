<?php declare(strict_types=1);

/**
 * User Manager Class
 * Gestione completa degli utenti con supporto multi-tenant
 *
 * @author Nexiosolution
 * @version 2.0.0
 * @since 2025-01-17
 */

namespace Collabora\Users;

use PDO;
use PDOException;
use Exception;
use InvalidArgumentException;
use Collabora\Auth\AuthenticationV2;
use Collabora\Auth\UserRole;
use Collabora\Auth\UserStatus;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_v2.php';

/**
 * Classe per la gestione degli utenti
 */
class UserManager {
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
     * Crea un nuovo utente
     *
     * @param array $data Dati dell'utente
     * @return array Utente creato
     * @throws Exception Se la creazione fallisce
     */
    public function createUser(array $data): array {
        // Verifica permessi
        if (!$this->auth->hasPermission('users.create')) {
            throw new Exception('Non hai i permessi per creare utenti');
        }

        // Valida dati obbligatori
        $this->validateUserData($data, true);

        try {
            $this->db->beginTransaction();

            // Verifica unicità email
            if ($this->emailExists($data['email'])) {
                throw new Exception('Email già registrata nel sistema');
            }

            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);

            // Determina il tenant predefinito
            $defaultTenantId = null;
            if (!empty($data['tenant_id'])) {
                $defaultTenantId = (int)$data['tenant_id'];
            } elseif (!$this->auth->isAdmin() && $this->currentTenantId) {
                $defaultTenantId = $this->currentTenantId;
            }

            // Inserisci utente
            $stmt = $this->db->prepare("
                INSERT INTO users (
                    email, password, first_name, last_name, role, is_system_admin,
                    tenant_id, status, phone, timezone, language, settings,
                    email_verified_at, created_by, created_at
                ) VALUES (
                    :email, :password, :first_name, :last_name, :role, :is_admin,
                    :tenant_id, :status, :phone, :timezone, :language, :settings,
                    :email_verified, :created_by, NOW()
                )
            ");

            $role = $data['role'] ?? UserRole::STANDARD_USER->value;
            $isSystemAdmin = isset($data['is_system_admin']) && $data['is_system_admin'] && $this->auth->isAdmin();

            $stmt->execute([
                'email' => strtolower(trim($data['email'])),
                'password' => $hashedPassword,
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'role' => $role,
                'is_admin' => $isSystemAdmin,
                'tenant_id' => $defaultTenantId,
                'status' => $data['status'] ?? UserStatus::ACTIVE->value,
                'phone' => $data['phone'] ?? null,
                'timezone' => $data['timezone'] ?? 'Europe/Rome',
                'language' => $data['language'] ?? 'it',
                'settings' => !empty($data['settings']) ? json_encode($data['settings']) : null,
                'email_verified' => $data['auto_verify'] ?? false ? date('Y-m-d H:i:s') : null,
                'created_by' => $this->currentUserId
            ]);

            $userId = (int)$this->db->lastInsertId();

            // Gestisci associazioni tenant
            if (!empty($data['tenant_associations'])) {
                $this->createTenantAssociations($userId, $data['tenant_associations']);
            } elseif ($defaultTenantId) {
                // Crea associazione predefinita
                $this->createTenantAssociation($userId, $defaultTenantId, [
                    'role_in_tenant' => $this->mapRoleToTenantRole($role),
                    'is_primary' => true
                ]);
            }

            // Log attività
            $this->logActivity('user.create', $userId, [
                'email' => $data['email'],
                'role' => $role,
                'created_by' => $this->currentUserId
            ]);

            $this->db->commit();

            // Restituisci utente creato
            return $this->getUserById($userId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Aggiorna un utente esistente
     *
     * @param int $userId ID dell'utente
     * @param array $data Dati da aggiornare
     * @return array Utente aggiornato
     * @throws Exception Se l'aggiornamento fallisce
     */
    public function updateUser(int $userId, array $data): array {
        // Verifica permessi
        $canEditAny = $this->auth->hasPermission('users.edit');
        $canEditOwn = $this->auth->hasPermission('users.edit_own');

        if (!$canEditAny && (!$canEditOwn || $userId !== $this->currentUserId)) {
            throw new Exception('Non hai i permessi per modificare questo utente');
        }

        // Recupera utente esistente
        $existingUser = $this->getUserById($userId);
        if (!$existingUser) {
            throw new Exception('Utente non trovato');
        }

        try {
            $this->db->beginTransaction();

            // Prepara campi da aggiornare
            $updates = [];
            $params = ['id' => $userId];

            // Campi modificabili da tutti
            $allowedFields = ['first_name', 'last_name', 'phone', 'timezone', 'language', 'settings'];

            // Campi modificabili solo da admin
            if ($this->auth->isAdmin()) {
                $allowedFields = array_merge($allowedFields, ['email', 'role', 'status', 'is_system_admin', 'tenant_id']);
            }

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = :$field";
                    $params[$field] = $data[$field];

                    // Gestione speciale per alcuni campi
                    if ($field === 'email') {
                        $params[$field] = strtolower(trim($params[$field]));
                        // Verifica unicità
                        if ($this->emailExists($params[$field], $userId)) {
                            throw new Exception('Email già in uso');
                        }
                    } elseif ($field === 'settings') {
                        $params[$field] = json_encode($params[$field]);
                    }
                }
            }

            // Gestione password
            if (!empty($data['password'])) {
                if ($userId !== $this->currentUserId && !$this->auth->isAdmin()) {
                    throw new Exception('Non puoi modificare la password di altri utenti');
                }

                $updates[] = "password = :password";
                $params['password'] = password_hash($data['password'], PASSWORD_ARGON2ID, [
                    'memory_cost' => 65536,
                    'time_cost' => 4,
                    'threads' => 3
                ]);
            }

            // Esegui aggiornamento se ci sono modifiche
            if (!empty($updates)) {
                $updates[] = "updated_at = NOW()";
                $updates[] = "updated_by = :updated_by";
                $params['updated_by'] = $this->currentUserId;

                $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            // Gestisci associazioni tenant (solo admin)
            if ($this->auth->isAdmin() && isset($data['tenant_associations'])) {
                $this->updateTenantAssociations($userId, $data['tenant_associations']);
            }

            // Log attività
            $changes = array_diff_assoc($data, (array)$existingUser);
            if (!empty($changes)) {
                $this->logActivity('user.update', $userId, [
                    'changes' => array_keys($changes),
                    'updated_by' => $this->currentUserId
                ]);
            }

            $this->db->commit();

            return $this->getUserById($userId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina un utente (soft delete)
     *
     * @param int $userId ID dell'utente
     * @return bool Successo
     * @throws Exception Se l'eliminazione fallisce
     */
    public function deleteUser(int $userId): bool {
        // Solo admin possono eliminare utenti
        if (!$this->auth->hasPermission('users.delete')) {
            throw new Exception('Non hai i permessi per eliminare utenti');
        }

        // Non permettere auto-eliminazione
        if ($userId === $this->currentUserId) {
            throw new Exception('Non puoi eliminare il tuo account');
        }

        // Verifica esistenza
        $user = $this->getUserById($userId);
        if (!$user) {
            throw new Exception('Utente non trovato');
        }

        // Non eliminare admin di sistema
        if ($user['is_system_admin']) {
            throw new Exception('Non puoi eliminare un amministratore di sistema');
        }

        try {
            $this->db->beginTransaction();

            // Soft delete
            $stmt = $this->db->prepare("
                UPDATE users
                SET deleted_at = NOW(),
                    status = 'inactive',
                    updated_by = :updated_by
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $userId,
                'updated_by' => $this->currentUserId
            ]);

            // Rimuovi sessioni attive
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);

            // Log attività
            $this->logActivity('user.delete', $userId, [
                'deleted_by' => $this->currentUserId,
                'user_email' => $user['email']
            ]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Ottiene un utente per ID
     *
     * @param int $userId ID dell'utente
     * @return array|null Dati utente o null
     */
    public function getUserById(int $userId): ?array {
        // Verifica permessi
        $canViewAny = $this->auth->hasPermission('users.view');
        $canViewOwn = $this->auth->hasPermission('users.view_own');

        if (!$canViewAny && (!$canViewOwn || $userId !== $this->currentUserId)) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.is_system_admin,
                   u.tenant_id, u.status, u.phone, u.timezone, u.language,
                   u.last_login_at, u.last_login_ip, u.email_verified_at,
                   u.settings, u.created_at, u.updated_at,
                   CONCAT(c.first_name, ' ', c.last_name) as created_by_name,
                   CONCAT(up.first_name, ' ', up.last_name) as updated_by_name
            FROM users u
            LEFT JOIN users c ON c.id = u.created_by
            LEFT JOIN users up ON up.id = u.updated_by
            WHERE u.id = :id
              AND u.deleted_at IS NULL
        ");

        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Aggiungi tenant associati
            $user['tenants'] = $this->getUserTenants($userId);

            // Decodifica settings
            if ($user['settings']) {
                $user['settings'] = json_decode($user['settings'], true);
            }

            // Rimuovi campi sensibili per utenti non admin
            if (!$this->auth->isAdmin() && $userId !== $this->currentUserId) {
                unset(
                    $user['last_login_ip'],
                    $user['settings']
                );
            }
        }

        return $user ?: null;
    }

    /**
     * Cerca utenti con filtri
     *
     * @param array $filters Filtri di ricerca
     * @param int $page Numero di pagina
     * @param int $limit Elementi per pagina
     * @return array Risultati paginati
     */
    public function searchUsers(array $filters = [], int $page = 1, int $limit = 20): array {
        // Verifica permessi
        if (!$this->auth->hasPermission('users.view')) {
            throw new Exception('Non hai i permessi per visualizzare gli utenti');
        }

        $where = ['u.deleted_at IS NULL'];
        $params = [];

        // Applica filtri
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(u.email LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
            $params['search'] = $search;
        }

        if (!empty($filters['role'])) {
            $where[] = "u.role = :role";
            $params['role'] = $filters['role'];
        }

        if (!empty($filters['status'])) {
            $where[] = "u.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['tenant_id']) && !$this->auth->isAdmin()) {
            // Non admin vedono solo utenti del proprio tenant
            $where[] = "EXISTS (
                SELECT 1 FROM user_tenant_associations uta
                WHERE uta.user_id = u.id AND uta.tenant_id = :tenant_id
            )";
            $params['tenant_id'] = $filters['tenant_id'];
        } elseif ($this->currentTenantId && !$this->auth->isAdmin()) {
            $where[] = "EXISTS (
                SELECT 1 FROM user_tenant_associations uta
                WHERE uta.user_id = u.id AND uta.tenant_id = :current_tenant
            )";
            $params['current_tenant'] = $this->currentTenantId;
        }

        // Conta totale
        $countSql = "SELECT COUNT(*) FROM users u WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Calcola paginazione
        $offset = ($page - 1) * $limit;
        $totalPages = (int)ceil($total / $limit);

        // Query principale
        $sql = "
            SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.is_system_admin,
                   u.status, u.tenant_id, u.last_login_at, u.created_at,
                   GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') as tenant_names
            FROM users u
            LEFT JOIN user_tenant_associations uta ON uta.user_id = u.id
            LEFT JOIN tenants t ON t.id = uta.tenant_id AND t.deleted_at IS NULL
            WHERE " . implode(' AND ', $where) . "
            GROUP BY u.id
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages
            ]
        ];
    }

    /**
     * Associa un utente a un tenant
     *
     * @param int $userId ID utente
     * @param int $tenantId ID tenant
     * @param array $options Opzioni di associazione
     * @return bool Successo
     */
    public function associateUserToTenant(int $userId, int $tenantId, array $options = []): bool {
        // Solo admin possono gestire associazioni
        if (!$this->auth->hasPermission('users.manage_tenants')) {
            throw new Exception('Non hai i permessi per gestire le associazioni tenant');
        }

        return $this->createTenantAssociation($userId, $tenantId, $options);
    }

    /**
     * Rimuove l'associazione utente-tenant
     *
     * @param int $userId ID utente
     * @param int $tenantId ID tenant
     * @return bool Successo
     */
    public function removeUserFromTenant(int $userId, int $tenantId): bool {
        // Solo admin possono rimuovere associazioni
        if (!$this->auth->hasPermission('users.manage_tenants')) {
            throw new Exception('Non hai i permessi per gestire le associazioni tenant');
        }

        try {
            // Verifica che non sia l'unico tenant dell'utente
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM user_tenant_associations
                WHERE user_id = :user_id
            ");
            $stmt->execute(['user_id' => $userId]);

            if ((int)$stmt->fetchColumn() <= 1) {
                throw new Exception('Un utente deve essere associato ad almeno un tenant');
            }

            // Rimuovi associazione
            $stmt = $this->db->prepare("
                DELETE FROM user_tenant_associations
                WHERE user_id = :user_id AND tenant_id = :tenant_id
            ");

            $result = $stmt->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);

            // Log attività
            $this->logActivity('user.tenant.remove', $userId, [
                'tenant_id' => $tenantId,
                'removed_by' => $this->currentUserId
            ]);

            return $result;

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Ottiene i tenant associati a un utente
     *
     * @param int $userId ID utente
     * @return array Lista tenant
     */
    private function getUserTenants(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT t.id, t.name, t.domain,
                   uta.role_in_tenant, uta.is_primary, uta.joined_at
            FROM user_tenant_associations uta
            JOIN tenants t ON t.id = uta.tenant_id
            WHERE uta.user_id = :user_id
              AND t.deleted_at IS NULL
            ORDER BY uta.is_primary DESC, t.name
        ");

        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crea un'associazione utente-tenant
     *
     * @param int $userId ID utente
     * @param int $tenantId ID tenant
     * @param array $options Opzioni
     * @return bool Successo
     */
    private function createTenantAssociation(int $userId, int $tenantId, array $options = []): bool {
        $stmt = $this->db->prepare("
            INSERT INTO user_tenant_associations
                (user_id, tenant_id, role_in_tenant, is_primary, permissions, invited_by)
            VALUES
                (:user_id, :tenant_id, :role, :is_primary, :permissions, :invited_by)
            ON DUPLICATE KEY UPDATE
                role_in_tenant = VALUES(role_in_tenant),
                is_primary = VALUES(is_primary),
                permissions = VALUES(permissions)
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'role' => $options['role_in_tenant'] ?? 'user',
            'is_primary' => $options['is_primary'] ?? false,
            'permissions' => !empty($options['permissions']) ? json_encode($options['permissions']) : null,
            'invited_by' => $this->currentUserId
        ]);
    }

    /**
     * Crea associazioni multiple per un utente
     *
     * @param int $userId ID utente
     * @param array $associations Lista di associazioni
     */
    private function createTenantAssociations(int $userId, array $associations): void {
        foreach ($associations as $assoc) {
            if (!empty($assoc['tenant_id'])) {
                $this->createTenantAssociation($userId, (int)$assoc['tenant_id'], $assoc);
            }
        }
    }

    /**
     * Aggiorna le associazioni tenant di un utente
     *
     * @param int $userId ID utente
     * @param array $associations Nuove associazioni
     */
    private function updateTenantAssociations(int $userId, array $associations): void {
        // Rimuovi associazioni esistenti non presenti nella nuova lista
        $tenantIds = array_column($associations, 'tenant_id');
        if (!empty($tenantIds)) {
            $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));
            $stmt = $this->db->prepare("
                DELETE FROM user_tenant_associations
                WHERE user_id = ? AND tenant_id NOT IN ($placeholders)
            ");
            $stmt->execute(array_merge([$userId], $tenantIds));
        }

        // Aggiungi o aggiorna nuove associazioni
        $this->createTenantAssociations($userId, $associations);
    }

    /**
     * Mappa il ruolo utente al ruolo tenant
     *
     * @param string $userRole Ruolo utente
     * @return string Ruolo nel tenant
     */
    private function mapRoleToTenantRole(string $userRole): string {
        return match($userRole) {
            UserRole::ADMIN->value => 'admin',
            UserRole::SPECIAL_USER->value => 'manager',
            default => 'user'
        };
    }

    /**
     * Verifica se un'email esiste già
     *
     * @param string $email Email da verificare
     * @param int|null $excludeUserId ID utente da escludere
     * @return bool True se esiste
     */
    private function emailExists(string $email, ?int $excludeUserId = null): bool {
        $sql = "SELECT COUNT(*) FROM users WHERE email = :email AND deleted_at IS NULL";
        $params = ['email' => strtolower(trim($email))];

        if ($excludeUserId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeUserId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Valida i dati utente
     *
     * @param array $data Dati da validare
     * @param bool $isCreate True se creazione
     * @throws InvalidArgumentException Se i dati non sono validi
     */
    private function validateUserData(array $data, bool $isCreate = false): void {
        // Email
        if ($isCreate || isset($data['email'])) {
            $email = $data['email'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Email non valida');
            }
        }

        // Password
        if ($isCreate || isset($data['password'])) {
            $password = $data['password'] ?? '';
            if (strlen($password) < 8) {
                throw new InvalidArgumentException('La password deve essere di almeno 8 caratteri');
            }
            if (!preg_match('/[A-Z]/', $password)) {
                throw new InvalidArgumentException('La password deve contenere almeno una maiuscola');
            }
            if (!preg_match('/[a-z]/', $password)) {
                throw new InvalidArgumentException('La password deve contenere almeno una minuscola');
            }
            if (!preg_match('/[0-9]/', $password)) {
                throw new InvalidArgumentException('La password deve contenere almeno un numero');
            }
            if (!preg_match('/[@$!%*?&]/', $password)) {
                throw new InvalidArgumentException('La password deve contenere almeno un carattere speciale');
            }
        }

        // Nome e cognome
        if ($isCreate) {
            if (empty($data['first_name']) || empty($data['last_name'])) {
                throw new InvalidArgumentException('Nome e cognome sono obbligatori');
            }
        }

        // Ruolo
        if (isset($data['role'])) {
            $validRoles = [
                UserRole::ADMIN->value,
                UserRole::SPECIAL_USER->value,
                UserRole::STANDARD_USER->value
            ];
            if (!in_array($data['role'], $validRoles)) {
                throw new InvalidArgumentException('Ruolo non valido');
            }
        }

        // Status
        if (isset($data['status'])) {
            $validStatuses = [
                UserStatus::ACTIVE->value,
                UserStatus::INACTIVE->value,
                UserStatus::LOCKED->value
            ];
            if (!in_array($data['status'], $validStatuses)) {
                throw new InvalidArgumentException('Stato non valido');
            }
        }
    }

    /**
     * Log attività utente
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
                    (:user_id, :tenant_id, :action, 'user', :entity_id, :metadata, :ip, :user_agent, :session_id)
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