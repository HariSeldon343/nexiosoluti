<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $pdo->prepare('SELECT c.id, c.name, c.type FROM chat_channels c INNER JOIN chat_channel_members m ON m.channel_id = c.id WHERE c.tenant_id = :tenant AND m.user_id = :user ORDER BY c.name');
        $stmt->execute([':tenant' => $tenantId, ':user' => $user['id']]);
        json_response(['channels' => $stmt->fetchAll()]);
    case 'POST':
        $data = get_json_input();
        verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $name = sanitize($data['name'] ?? '');
        $type = in_array($data['type'] ?? 'public', ['public', 'private', 'direct'], true) ? $data['type'] : 'public';
        if (!$name && $type !== 'direct') {
            json_response(['success' => false, 'error' => 'Nome obbligatorio'], 422);
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO chat_channels (tenant_id, type, name, description, created_by) VALUES (:tenant, :type, :name, :description, :user)');
            $stmt->execute([
                ':tenant' => $tenantId,
                ':type' => $type,
                ':name' => $name,
                ':description' => $data['description'] ?? null,
                ':user' => $user['id'],
            ]);
            $channelId = (int)$pdo->lastInsertId();
            $members = $data['members'] ?? [$user['id']];
            $memberStmt = $pdo->prepare('INSERT INTO chat_channel_members (channel_id, user_id) VALUES (:channel, :member)');
            foreach ($members as $member) {
                $memberStmt->execute([':channel' => $channelId, ':member' => $member]);
            }
            $pdo->commit();
            json_response(['success' => true, 'id' => $channelId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            json_response(['success' => false, 'error' => 'Impossibile creare canale'], 500);
        }
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}
