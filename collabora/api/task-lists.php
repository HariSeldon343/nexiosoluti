<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $pdo->prepare('SELECT id, name, color, position FROM task_lists WHERE tenant_id = :tenant ORDER BY position, name');
        $stmt->execute([':tenant' => $tenantId]);
        json_response(['lists' => $stmt->fetchAll()]);
    case 'POST':
        $data = get_json_input();
        verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $name = sanitize($data['name'] ?? '');
        $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color'] ?? '') ? $data['color'] : '#6B7280';
        if (!$name) {
            json_response(['success' => false, 'error' => 'Nome obbligatorio'], 422);
        }
        $stmt = $pdo->prepare('INSERT INTO task_lists (tenant_id, name, description, color, position, created_by) VALUES (:tenant, :name, :description, :color, :position, :user)');
        $stmt->execute([
            ':tenant' => $tenantId,
            ':name' => $name,
            ':description' => $data['description'] ?? null,
            ':color' => $color,
            ':position' => (int)($data['position'] ?? 0),
            ':user' => $user['id'],
        ]);
        json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}
