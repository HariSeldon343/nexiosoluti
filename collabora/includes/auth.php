<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

class Auth
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function login(string $email, string $password): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !(bool)$user['is_active']) {
            respondWithError('Credenziali non valide', 401);
        }

        if (!password_verify($password, $user['password_hash'])) {
            respondWithError('Credenziali non valide', 401);
        }

        session_regenerate_id(true);

        $accessibleTenants = $this->fetchAccessibleTenants((int)$user['id'], (int)$user['tenant_id'], $user['role']);
        $tenantId = (int)($user['tenant_id']);
        if (!in_array($tenantId, $accessibleTenants, true) && !empty($accessibleTenants)) {
            $tenantId = $accessibleTenants[0];
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['tenants'] = $accessibleTenants;

        $this->updateLastLogin((int)$user['id']);
        logActivity($this->pdo, $tenantId, (int)$user['id'], 'login', 'user', (int)$user['id'], []);

        return $this->formatUser($user, $accessibleTenants, $tenantId);
    }

    public function logout(): void
    {
        if (!empty($_SESSION['user_id'])) {
            try {
                logActivity($this->pdo, (int)($_SESSION['tenant_id'] ?? 0), (int)$_SESSION['user_id'], 'logout', 'user', (int)$_SESSION['user_id']);
            } catch (\PDOException $e) {
                // Ignoriamo errori di logging in logout
            }
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function check(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $accessibleTenants = $this->fetchAccessibleTenants((int)$user['id'], (int)$user['tenant_id'], $user['role']);
        $currentTenantId = (int)($_SESSION['tenant_id'] ?? $user['tenant_id']);

        if (!in_array($currentTenantId, $accessibleTenants, true) && !empty($accessibleTenants)) {
            $currentTenantId = $accessibleTenants[0];
            $_SESSION['tenant_id'] = $currentTenantId;
        }

        return $this->formatUser($user, $accessibleTenants, $currentTenantId);
    }

    public function requireAuth(): array
    {
        $user = $this->check();
        if (!$user) {
            jsonResponse(['authenticated' => false, 'message' => 'Sessione non valida'], 401);
        }
        return $user;
    }

    public function getCurrentTenantId(): int
    {
        return (int)($_SESSION['tenant_id'] ?? 0);
    }

    public function switchTenant(int $tenantId): array
    {
        $user = $this->requireAuth();
        if (!in_array($tenantId, $user['tenants'], true)) {
            respondWithError('Accesso al tenant negato', 403);
        }
        $_SESSION['tenant_id'] = $tenantId;
        return $this->check() ?? $user;
    }

    private function updateLastLogin(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
        $stmt->execute(['id' => $userId]);
    }

    private function fetchAccessibleTenants(int $userId, int $primaryTenantId, string $role): array
    {
        $tenants = [$primaryTenantId];

        $stmt = $this->pdo->prepare('SELECT tenant_id FROM user_tenant_access WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $extra = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($extra as $tenantId) {
            $tenantId = (int)$tenantId;
            if (!in_array($tenantId, $tenants, true)) {
                $tenants[] = $tenantId;
            }
        }

        if ($role === 'special') {
            // Gli utenti speciali hanno accesso a tutti i tenant presenti in tabella
            $stmt = $this->pdo->query('SELECT id FROM tenants');
            $all = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($all as $tenantId) {
                $tenantId = (int)$tenantId;
                if (!in_array($tenantId, $tenants, true)) {
                    $tenants[] = $tenantId;
                }
            }
        }

        sort($tenants);
        return $tenants;
    }

    private function formatUser(array $user, array $tenants, int $currentTenantId): array
    {
        $tenantData = $this->fetchTenantData($tenants);
        return [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'tenant_id' => $currentTenantId,
            'tenant_ids' => $tenants,
            'tenants' => $tenantData,
            'preferences' => json_decode($user['preferences'] ?? '{}', true) ?: [],
        ];
    }

    private function fetchTenantData(array $tenantIds): array
    {
        if (empty($tenantIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id, code, name, logo_path FROM tenants WHERE id IN ($placeholders)");
        $stmt->execute($tenantIds);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[] = [
                'id' => (int)$row['id'],
                'code' => $row['code'],
                'name' => $row['name'],
                'logo_path' => $row['logo_path'],
            ];
        }
        return $result;
    }
}
