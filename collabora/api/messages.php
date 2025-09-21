<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $channelId = filter_input(INPUT_GET, 'channel_id', FILTER_VALIDATE_INT);
        $lastId = filter_input(INPUT_GET, 'last_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
        if (!$channelId) {
            json_response(['messages' => []]);
        }
        $stmt = $pdo->prepare('SELECT m.id, m.content, m.user_id, m.created_at, u.name FROM chat_messages m INNER JOIN users u ON u.id = m.user_id WHERE m.channel_id = :channel AND m.id > :last ORDER BY m.id ASC LIMIT 100');
        $stmt->execute([':channel' => $channelId, ':last' => $lastId]);
        json_response(['messages' => $stmt->fetchAll()]);
    case 'POST':
        $data = get_json_input();
        verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $channelId = (int)($data['channel_id'] ?? 0);
        $content = trim($data['content'] ?? '');
        if (!$channelId || $content === '') {
            json_response(['success' => false, 'error' => 'Dati mancanti'], 422);
        }
        $stmt = $pdo->prepare('INSERT INTO chat_messages (channel_id, user_id, content) VALUES (:channel, :user, :content)');
        $stmt->execute([
            ':channel' => $channelId,
            ':user' => $user['id'],
            ':content' => $content,
        ]);
        $messageId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO chat_presence (user_id, tenant_id, status, last_activity) VALUES (:user, :tenant, "online", NOW()) ON DUPLICATE KEY UPDATE last_activity = NOW(), status = VALUES(status)')->execute([
            ':user' => $user['id'],
            ':tenant' => $tenantId,
        ]);
        json_response(['success' => true, 'id' => $messageId]);
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}
