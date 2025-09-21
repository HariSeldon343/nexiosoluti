<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
$user = $auth->requireAuth();
$tenantId = $auth->getCurrentTenantId();
$pdo = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($method) {
    case 'GET':
        $unread = filter_var($_GET['unread'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $query = 'SELECT id, type, title, message, data, action_url, is_read, created_at FROM notifications WHERE user_id = :user AND tenant_id = :tenant';
        if ($unread) {
            $query .= ' AND is_read = 0';
        }
        $query .= ' ORDER BY created_at DESC LIMIT 50';
        $stmt = $pdo->prepare($query);
        $stmt->execute(['user' => $user['id'], 'tenant' => $tenantId]);
        $notifications = $stmt->fetchAll();
        jsonResponse(['success' => true, 'notifications' => array_map(static fn($row) => [
            'id' => (int)$row['id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'data' => json_decode($row['data'], true),
            'action_url' => $row['action_url'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at'],
        ], $notifications)]);
        break;

    case 'PUT':
        requireCsrfToken();
        $notificationId = sanitizeInt($_GET['id'] ?? null);
        $action = $_GET['action'] ?? 'read';
        if (!$notificationId) {
            respondWithError('Notifica non valida', 422);
        }
        if ($action === 'read') {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user');
            $stmt->execute(['id' => $notificationId, 'user' => $user['id']]);
        } elseif ($action === 'unread') {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 0 WHERE id = :id AND user_id = :user');
            $stmt->execute(['id' => $notificationId, 'user' => $user['id']]);
        } else {
            respondWithError('Azione non supportata', 422);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}
