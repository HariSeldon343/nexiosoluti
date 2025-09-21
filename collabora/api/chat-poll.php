<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];
$channelsParam = filter_input(INPUT_GET, 'channels', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
$lastId = filter_input(INPUT_GET, 'last_message_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$channelIds = array_filter(array_map('intval', explode(',', $channelsParam)));

if (!$channelIds) {
    $stmt = $pdo->prepare('SELECT channel_id FROM chat_channel_members WHERE user_id = :user');
    $stmt->execute([':user' => $user['id']]);
    $channelIds = array_map('intval', array_column($stmt->fetchAll(), 'channel_id'));
}

$placeholders = implode(',', array_fill(0, count($channelIds), '?'));
$messages = [];
if ($channelIds) {
    $stmt = $pdo->prepare('SELECT m.id, m.channel_id, m.content, m.created_at, u.name FROM chat_messages m INNER JOIN users u ON u.id = m.user_id WHERE m.channel_id IN (' . $placeholders . ') AND m.id > ? ORDER BY m.id ASC LIMIT 200');
    $stmt->execute([...$channelIds, $lastId]);
    $messages = $stmt->fetchAll();
}

$presenceStmt = $pdo->prepare('SELECT user_id, status, last_activity FROM chat_presence WHERE tenant_id = :tenant');
$presenceStmt->execute([':tenant' => $tenantId]);
$presence = $presenceStmt->fetchAll();

json_response([
    'messages' => $messages,
    'presence' => $presence,
    'typing' => [],
]);
