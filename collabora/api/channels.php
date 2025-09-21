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
        $stmt = $pdo->prepare('SELECT c.id, c.type, c.name, c.description FROM chat_channels c INNER JOIN chat_channel_members m ON c.id = m.channel_id WHERE c.tenant_id = :tenant AND m.user_id = :user ORDER BY c.name');
        $stmt->execute(['tenant' => $tenantId, 'user' => $user['id']]);
        $channels = $stmt->fetchAll();
        jsonResponse(['success' => true, 'channels' => array_map(static fn($row) => [
            'id' => (int)$row['id'],
            'type' => $row['type'],
            'name' => $row['name'],
            'description' => $row['description'],
        ], $channels)]);
        break;

    case 'POST':
        requireCsrfToken();
        $data = getJsonInput();
        $type = sanitizeString($data['type'] ?? 'public');
        $name = sanitizeString($data['name'] ?? '');
        if (!in_array($type, ['public', 'private', 'direct'], true)) {
            respondWithError('Tipo canale non valido', 422);
        }
        if ($type !== 'direct' && !$name) {
            respondWithError('Nome canale obbligatorio', 422);
        }
        $stmt = $pdo->prepare('INSERT INTO chat_channels (tenant_id, type, name, description, created_by) VALUES (:tenant, :type, :name, :description, :user)');
        $stmt->execute([
            'tenant' => $tenantId,
            'type' => $type,
            'name' => $name,
            'description' => sanitizeString($data['description'] ?? ''),
            'user' => $user['id'],
        ]);
        $channelId = (int)$pdo->lastInsertId();
        $members = array_unique(array_merge([$user['id']], array_filter(array_map('sanitizeInt', $data['members'] ?? []))));
        foreach ($members as $memberId) {
            if (!$memberId) {
                continue;
            }
            $pdo->prepare('INSERT IGNORE INTO chat_channel_members (channel_id, user_id) VALUES (:channel, :user)')->execute([
                'channel' => $channelId,
                'user' => $memberId,
            ]);
        }
        jsonResponse(['success' => true, 'channel_id' => $channelId], 201);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}
