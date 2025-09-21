<?php
require_once __DIR__ . '/../config.php';

$stmt = $pdo->prepare('SELECT id, user_id, title, message FROM notifications WHERE is_read = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)');
$stmt->execute();
foreach ($stmt->fetchAll() as $notification) {
    // Placeholder invio email
}
