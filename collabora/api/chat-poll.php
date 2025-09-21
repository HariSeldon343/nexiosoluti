<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
$user = $auth->requireAuth();
$tenantId = $auth->getCurrentTenantId();
$pdo = Database::getInstance()->getConnection();

set_time_limit(40);
ignore_user_abort(true);

$channelsParam = $_GET['channels'] ?? '';
$channelIds = [];
if ($channelsParam !== '') {
    foreach (explode(',', $channelsParam) as $channel) {
        $channelId = sanitizeInt($channel);
        if ($channelId) {
            $channelIds[] = $channelId;
        }
    }
}
if (empty($channelIds)) {
    $stmt = $pdo->prepare('SELECT channel_id FROM chat_channel_members WHERE user_id = :user');
    $stmt->execute(['user' => $user['id']]);
    $channelIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

if (empty($channelIds)) {
    jsonResponse(['messages' => [], 'presence' => [], 'typing' => []]);
}

$lastMessageId = sanitizeInt($_GET['last_message_id'] ?? 0) ?? 0;
$start = time();
$messages = [];
$interval = 2;

$placeholders = implode(',', array_fill(0, count($channelIds), '?'));

$pdo->prepare('REPLACE INTO chat_presence (user_id, tenant_id, status, last_activity) VALUES (:user, :tenant, "online", NOW())')->execute([
    'user' => $user['id'],
    'tenant' => $tenantId,
]);

while (true) {
    $stmt = $pdo->prepare("SELECT m.id, m.channel_id, m.content, m.user_id, m.created_at, u.name FROM chat_messages m INNER JOIN users u ON m.user_id = u.id WHERE m.channel_id IN ($placeholders) AND m.id > :last_id ORDER BY m.id ASC");
    foreach ($channelIds as $index => $channelId) {
        $stmt->bindValue($index + 1, $channelId, PDO::PARAM_INT);
    }
    $stmt->bindValue(':last_id', $lastMessageId, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll();
    if (!empty($messages) || time() - $start > 25) {
        break;
    }
    usleep($interval * 500000);
}

$presenceStmt = $pdo->prepare('SELECT p.user_id, u.name, p.status, p.last_activity FROM chat_presence p INNER JOIN users u ON p.user_id = u.id WHERE p.tenant_id = :tenant ORDER BY u.name');
$presenceStmt->execute(['tenant' => $tenantId]);
$presence = $presenceStmt->fetchAll();

jsonResponse([
    'messages' => array_map(static function ($row) {
        return [
            'id' => (int)$row['id'],
            'channel_id' => (int)$row['channel_id'],
            'content' => html_entity_decode($row['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'user_id' => (int)$row['user_id'],
            'user_name' => $row['name'],
            'created_at' => $row['created_at'],
        ];
    }, $messages),
    'presence' => array_map(static function ($row) {
        return [
            'user_id' => (int)$row['user_id'],
            'name' => $row['name'],
            'status' => $row['status'],
            'last_activity' => $row['last_activity'],
        ];
    }, $presence),
    'typing' => [],
]);
