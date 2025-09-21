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
        $channelId = sanitizeInt($_GET['channel_id'] ?? null);
        if (!$channelId) {
            respondWithError('Canale non valido', 422);
        }
        ensureMembership($pdo, $channelId, $user['id']);
        $lastId = sanitizeInt($_GET['last_id'] ?? 0) ?? 0;
        $stmt = $pdo->prepare('SELECT m.id, m.channel_id, m.content, m.user_id, m.created_at, u.name FROM chat_messages m INNER JOIN chat_channel_members cm ON m.channel_id = cm.channel_id INNER JOIN users u ON m.user_id = u.id WHERE m.channel_id = :channel AND cm.user_id = :user AND m.id > :last_id ORDER BY m.id ASC');
        $stmt->execute(['channel' => $channelId, 'user' => $user['id'], 'last_id' => $lastId]);
        $messages = $stmt->fetchAll();
        jsonResponse(['success' => true, 'messages' => array_map('formatMessage', $messages)]);
        break;

    case 'POST':
        requireCsrfToken();
        $data = getJsonInput();
        $channelId = sanitizeInt($data['channel_id'] ?? null);
        if (!$channelId) {
            respondWithError('Canale non valido', 422);
        }
        ensureMembership($pdo, $channelId, $user['id']);
        $content = trim($data['content'] ?? '');
        if ($content === '') {
            respondWithError('Messaggio vuoto', 422);
        }
        $stmt = $pdo->prepare('INSERT INTO chat_messages (channel_id, user_id, content) VALUES (:channel, :user, :content)');
        $stmt->execute([
            'channel' => $channelId,
            'user' => $user['id'],
            'content' => htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);
        $messageId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE chat_channel_members SET last_read_message_id = :message WHERE channel_id = :channel AND user_id = :user')->execute([
            'message' => $messageId,
            'channel' => $channelId,
            'user' => $user['id'],
        ]);
        $pdo->prepare('REPLACE INTO chat_presence (user_id, tenant_id, status, last_activity) VALUES (:user, :tenant, "online", NOW())')->execute([
            'user' => $user['id'],
            'tenant' => $tenantId,
        ]);
        jsonResponse(['success' => true, 'message_id' => $messageId], 201);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}

function ensureMembership(\PDO $pdo, int $channelId, int $userId): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM chat_channel_members WHERE channel_id = :channel AND user_id = :user');
    $stmt->execute(['channel' => $channelId, 'user' => $userId]);
    if ((int)$stmt->fetchColumn() === 0) {
        respondWithError('Accesso al canale negato', 403);
    }
}

function formatMessage(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'channel_id' => isset($row['channel_id']) ? (int)$row['channel_id'] : null,
        'content' => html_entity_decode($row['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'user_id' => (int)$row['user_id'],
        'user_name' => $row['name'],
        'created_at' => $row['created_at'],
    ];
}
