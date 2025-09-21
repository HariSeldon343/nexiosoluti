<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $fileId = filter_input(INPUT_GET, 'file_id', FILTER_VALIDATE_INT);
    if (!$fileId) {
        json_response(['comments' => []]);
    }
    $stmt = $pdo->prepare('SELECT c.id, c.comment, c.parent_comment_id, c.status, c.created_at, u.name FROM file_comments c INNER JOIN files f ON f.id = c.file_id INNER JOIN users u ON u.id = c.user_id WHERE c.file_id = :file AND f.tenant_id = :tenant ORDER BY c.created_at ASC');
    $stmt->execute([':file' => $fileId, ':tenant' => $tenantId]);
    json_response(['comments' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_json_input();
    verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $fileId = (int)($data['file_id'] ?? 0);
    $comment = trim($data['comment'] ?? '');
    if (!$fileId || $comment === '') {
        json_response(['success' => false, 'error' => 'Dati mancanti'], 422);
    }
    $stmt = $pdo->prepare('INSERT INTO file_comments (file_id, user_id, comment, parent_comment_id, x_position, y_position, page_number) VALUES (:file, :user, :comment, :parent, :x, :y, :page)');
    $stmt->execute([
        ':file' => $fileId,
        ':user' => $user['id'],
        ':comment' => $comment,
        ':parent' => $data['parent_comment_id'] ?? null,
        ':x' => $data['x_position'] ?? null,
        ':y' => $data['y_position'] ?? null,
        ':page' => $data['page_number'] ?? null,
    ]);
    json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
}

json_response(['error' => 'Metodo non supportato'], 405);
