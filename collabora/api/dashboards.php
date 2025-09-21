<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $pdo->prepare('SELECT id, name, layout, is_default FROM dashboards WHERE tenant_id = :tenant AND user_id = :user ORDER BY created_at DESC');
        $stmt->execute([':tenant' => $tenantId, ':user' => $user['id']]);
        json_response(['dashboards' => $stmt->fetchAll()]);
    case 'POST':
        $data = get_json_input();
        verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $name = sanitize($data['name'] ?? '');
        if (!$name) {
            json_response(['success' => false, 'error' => 'Nome obbligatorio'], 422);
        }
        $stmt = $pdo->prepare('INSERT INTO dashboards (user_id, tenant_id, name, layout, is_default) VALUES (:user, :tenant, :name, :layout, :default)');
        $stmt->execute([
            ':user' => $user['id'],
            ':tenant' => $tenantId,
            ':name' => $name,
            ':layout' => json_encode($data['layout'] ?? []),
            ':default' => !empty($data['is_default']) ? 1 : 0,
        ]);
        json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}
