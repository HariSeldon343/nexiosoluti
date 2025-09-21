<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $pdo->prepare('SELECT id, name, color, is_default FROM calendars WHERE tenant_id = :tenant ORDER BY name');
        $stmt->execute([':tenant' => $tenantId]);
        json_response(['calendars' => $stmt->fetchAll()]);
    case 'POST':
        $data = get_json_input();
        verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $name = sanitize($data['name'] ?? '');
        $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color'] ?? '') ? $data['color'] : '#2563EB';
        $isDefault = !empty($data['is_default']) ? 1 : 0;
        if (!$name) {
            json_response(['success' => false, 'error' => 'Nome obbligatorio'], 422);
        }
        if ($isDefault) {
            $pdo->prepare('UPDATE calendars SET is_default = 0 WHERE tenant_id = :tenant')->execute([':tenant' => $tenantId]);
        }
        $stmt = $pdo->prepare('INSERT INTO calendars (tenant_id, name, color, is_default, created_by) VALUES (:tenant, :name, :color, :default, :user)');
        $stmt->execute([
            ':tenant' => $tenantId,
            ':name' => $name,
            ':color' => $color,
            ':default' => $isDefault,
            ':user' => $user['id'],
        ]);
        json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}
