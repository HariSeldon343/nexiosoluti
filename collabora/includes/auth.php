<?php
/**
 * Gestione autenticazione utenti
 */

declare(strict_types=1);

class Auth
{
    public function __construct(private PDO $pdo)
    {
    }

    public function login(string $tenantCode, string $email, string $password): ?array
    {
        $tenantStmt = $this->pdo->prepare('SELECT * FROM tenants WHERE code = :code');
        $tenantStmt->execute([':code' => $tenantCode]);
        $tenant = $tenantStmt->fetch();
        if (!$tenant) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE tenant_id = :tenant_id AND email = :email AND is_active = 1');
        $stmt->execute([
            ':tenant_id' => $tenant['id'],
            ':email' => $email,
        ]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        $this->updateLastLogin((int)$user['id']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['tenant_id'] = (int)$tenant['id'];
        ensure_csrf_token();

        return $this->formatUser($user, $tenant);
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function check(): ?array
    {
        if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT u.*, t.name AS tenant_name, t.code AS tenant_code FROM users u INNER JOIN tenants t ON t.id = u.tenant_id WHERE u.id = :id AND u.tenant_id = :tenant_id');
        $stmt->execute([
            ':id' => $_SESSION['user_id'],
            ':tenant_id' => $_SESSION['tenant_id'],
        ]);
        $user = $stmt->fetch();
        if (!$user) {
            return null;
        }
        ensure_csrf_token();
        return $this->formatUser($user, $user);
    }

    public function requireRole(array $user, array $roles): void
    {
        if (!in_array($user['role'], $roles, true)) {
            json_response(['error' => 'Accesso negato'], 403);
        }
    }

    private function updateLastLogin(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
        $stmt->execute([':id' => $userId]);
    }

    private function formatUser(array $user, array $tenant): array
    {
        return [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'tenant' => [
                'id' => (int)$tenant['tenant_id'] ?? (int)$tenant['id'],
                'name' => $tenant['tenant_name'] ?? $tenant['name'] ?? '',
                'code' => $tenant['tenant_code'] ?? $tenant['code'] ?? '',
            ],
            'csrf_token' => $_SESSION['csrf_token'] ?? null,
        ];
    }
}
