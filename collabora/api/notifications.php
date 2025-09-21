<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $unread = filter_input(INPUT_GET, 'unread', FILTER_VALIDATE_BOOLEAN);
        $sql = 'SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = :user AND tenant_id = :tenant';
        if ($unread) {
            $sql .= ' AND is_read = 0';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 50';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user' => $user['id'], ':tenant' => $tenantId]);
        json_response(['notifications' => $stmt->fetchAll()]);
    case 'PUT':
        parse_str(file_get_contents('php://input'), $input);
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        $action = $input['action'] ?? null;
        if ($action === 'read' && $id) {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user');
            $stmt->execute([':id' => $id, ':user' => $user['id']]);
            json_response(['success' => true]);
        }
        json_response(['success' => false, 'error' => 'Azione non valida'], 422);
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}
