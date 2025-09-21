<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare('SELECT id, user_id, title FROM notifications WHERE is_read = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)');
$stmt->execute();
$pending = $stmt->fetchAll();
foreach ($pending as $notification) {
    $log = sprintf("[%s] Promemoria notifica per utente #%d: %s\n", date('Y-m-d H:i:s'), $notification['user_id'], $notification['title']);
    file_put_contents(BASE_PATH . '/temp/notification-log.txt', $log, FILE_APPEND);
}
