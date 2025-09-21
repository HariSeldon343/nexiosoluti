<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
$user = $auth->requireAuth();
$tenantId = $auth->getCurrentTenantId();
$pdo = Database::getInstance()->getConnection();

if (!in_array($user['role'], ['admin', 'special'], true)) {
    respondWithError('Permesso negato', 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($method) {
    case 'GET':
        $stmt = $pdo->prepare('SELECT id, email, name, role, is_active, created_at FROM users WHERE tenant_id = :tenant ORDER BY created_at DESC');
        $stmt->execute(['tenant' => $tenantId]);
        $users = $stmt->fetchAll();
        jsonResponse(['success' => true, 'users' => array_map(static fn($row) => [
            'id' => (int)$row['id'],
            'email' => $row['email'],
            'name' => $row['name'],
            'role' => $row['role'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'],
        ], $users)]);
        break;

    case 'POST':
        requireCsrfToken();
        $data = getJsonInput();
        $email = sanitizeEmail($data['email'] ?? null);
        $name = sanitizeString($data['name'] ?? '');
        $role = sanitizeString($data['role'] ?? 'user');
        $password = $data['password'] ?? null;
        $targetTenant = isset($data['tenant_id']) ? sanitizeInt($data['tenant_id']) : $tenantId;
        if (!$email || !$name || !$password) {
            respondWithError('Dati utente mancanti', 422);
        }
        if (!in_array($role, ['admin', 'special', 'user'], true)) {
            respondWithError('Ruolo non valido', 422);
        }
        if (!in_array($targetTenant, $user['tenant_ids'], true)) {
            respondWithError('Tenant non consentito', 403);
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = :tenant AND email = :email');
        $stmt->execute(['tenant' => $targetTenant, 'email' => $email]);
        if ((int)$stmt->fetchColumn() > 0) {
            respondWithError('Email già in uso', 409);
        }
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (tenant_id, email, password_hash, name, role, is_active, created_at) VALUES (:tenant, :email, :hash, :name, :role, :active, NOW())');
        $stmt->execute([
            'tenant' => $targetTenant,
            'email' => $email,
            'hash' => $passwordHash,
            'name' => $name,
            'role' => $role,
            'active' => !empty($data['is_active']) ? 1 : 0,
        ]);
        $newUserId = (int)$pdo->lastInsertId();
        $extraTenants = array_filter(array_map('sanitizeInt', $data['extra_tenants'] ?? []));
        foreach ($extraTenants as $extraTenantId) {
            if (!in_array($extraTenantId, $user['tenant_ids'], true)) {
                continue;
            }
            $pdo->prepare('INSERT IGNORE INTO user_tenant_access (user_id, tenant_id, granted_by) VALUES (:user, :tenant, :by)')->execute([
                'user' => $newUserId,
                'tenant' => $extraTenantId,
                'by' => $user['id'],
            ]);
        }
        logActivity($pdo, $tenantId, $user['id'], 'create', 'user', $newUserId, ['email' => $email]);
        jsonResponse(['success' => true, 'user_id' => $newUserId], 201);
        break;

    case 'PUT':
    case 'PATCH':
        requireCsrfToken();
        $userId = sanitizeInt($_GET['id'] ?? null);
        if (!$userId) {
            respondWithError('Utente non valido', 422);
        }
        parse_str(file_get_contents('php://input'), $rawInput);
        $data = array_merge($rawInput, getJsonInput());
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $userId, 'tenant' => $tenantId]);
        $target = $stmt->fetch();
        if (!$target) {
            respondWithError('Utente non trovato', 404);
        }
        $fields = [];
        $params = ['id' => $userId];
        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params['name'] = sanitizeString($data['name']);
        }
        if (isset($data['role'])) {
            $role = sanitizeString($data['role']);
            if (!in_array($role, ['admin', 'special', 'user'], true)) {
                respondWithError('Ruolo non valido', 422);
            }
            $fields[] = 'role = :role';
            $params['role'] = $role;
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = :active';
            $params['active'] = (int)(bool)$data['is_active'];
        }
        if (!empty($data['password'])) {
            $fields[] = 'password_hash = :hash';
            $params['hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        if ($fields) {
            $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id')->execute($params);
        }
        if (isset($data['extra_tenants']) && is_array($data['extra_tenants'])) {
            $pdo->prepare('DELETE FROM user_tenant_access WHERE user_id = :id')->execute(['id' => $userId]);
            $extraTenants = array_filter(array_map('sanitizeInt', $data['extra_tenants']));
            foreach ($extraTenants as $extraTenantId) {
                if (!in_array($extraTenantId, $user['tenant_ids'], true)) {
                    continue;
                }
                $pdo->prepare('INSERT INTO user_tenant_access (user_id, tenant_id, granted_by) VALUES (:user, :tenant, :by)')->execute([
                    'user' => $userId,
                    'tenant' => $extraTenantId,
                    'by' => $user['id'],
                ]);
            }
        }
        logActivity($pdo, $tenantId, $user['id'], 'update', 'user', $userId);
        jsonResponse(['success' => true]);
        break;

    case 'DELETE':
        requireCsrfToken();
        $userId = sanitizeInt($_GET['id'] ?? null);
        if (!$userId) {
            respondWithError('Utente non valido', 422);
        }
        if ($userId === $user['id']) {
            respondWithError('Impossibile eliminare il proprio account', 409);
        }
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $userId, 'tenant' => $tenantId]);
        if ($stmt->rowCount() === 0) {
            respondWithError('Utente non trovato', 404);
        }
        logActivity($pdo, $tenantId, $user['id'], 'delete', 'user', $userId);
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}
