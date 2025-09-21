<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$auth->requireRole($user, ['admin', 'special']);
$tenantId = (int)$user['tenant']['id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $pdo->prepare('SELECT id, email, name, role, is_active, last_login FROM users WHERE tenant_id = :tenant ORDER BY created_at DESC');
        $stmt->execute([':tenant' => $tenantId]);
        json_response(['users' => $stmt->fetchAll()]);
    case 'POST':
        $data = get_json_input();
        verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $name = sanitize($data['name'] ?? '');
        $role = in_array($data['role'] ?? 'user', ['admin', 'special', 'user'], true) ? $data['role'] : 'user';
        $password = $data['password'] ?? '';
        if (!$email || !$name || strlen($password) < 6) {
            json_response(['success' => false, 'error' => 'Dati non validi'], 422);
        }
        $stmt = $pdo->prepare('INSERT INTO users (tenant_id, email, password_hash, name, role) VALUES (:tenant, :email, :hash, :name, :role)');
        $stmt->execute([
            ':tenant' => $tenantId,
            ':email' => $email,
            ':hash' => password_hash($password, PASSWORD_BCRYPT),
            ':name' => $name,
            ':role' => $role,
        ]);
        json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    case 'PUT':
        parse_str(file_get_contents('php://input'), $input);
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        $name = isset($input['name']) ? sanitize($input['name']) : null;
        $role = isset($input['role']) && in_array($input['role'], ['admin', 'special', 'user'], true) ? $input['role'] : null;
        $isActive = isset($input['is_active']) ? (int)$input['is_active'] : null;
        if (!$id) {
            json_response(['success' => false, 'error' => 'ID mancante'], 422);
        }
        $fields = [];
        $params = [':id' => $id, ':tenant' => $tenantId];
        if ($name !== null) {
            $fields[] = 'name = :name';
            $params[':name'] = $name;
        }
        if ($role !== null) {
            $fields[] = 'role = :role';
            $params[':role'] = $role;
        }
        if ($isActive !== null) {
            $fields[] = 'is_active = :active';
            $params[':active'] = $isActive;
        }
        if (!$fields) {
            json_response(['success' => false, 'error' => 'Nessun campo da aggiornare'], 422);
        }
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :tenant';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_response(['success' => true]);
    case 'DELETE':
        parse_str(file_get_contents('php://input'), $input);
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) {
            json_response(['success' => false, 'error' => 'ID mancante'], 422);
        }
        $stmt = $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute([':id' => $id, ':tenant' => $tenantId]);
        json_response(['success' => true]);
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}
